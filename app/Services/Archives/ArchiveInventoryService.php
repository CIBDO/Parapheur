<?php

namespace App\Services\Archives;

use App\Models\ArchiveBox;
use App\Models\ArchiveInventory;
use App\Models\ArchiveInventoryItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchiveInventoryService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ArchiveInventory
    {
        return DB::transaction(function () use ($actor, $data) {
            $inventory = ArchiveInventory::query()->create([
                'reference' => 'RC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'title' => $data['title'],
                'status' => 'brouillon',
                'structure_id' => (int) $data['structure_id'],
                'location_id' => $data['location_id'] ?? null,
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $boxesQuery = ArchiveBox::query()->where('structure_id', $inventory->structure_id);
            if ($inventory->location_id) {
                $boxesQuery->where('location_id', $inventory->location_id);
            }

            $boxes = $boxesQuery->get();
            foreach ($boxes as $box) {
                ArchiveInventoryItem::query()->create([
                    'inventory_id' => $inventory->id,
                    'box_id' => $box->id,
                    'expected_location_id' => $box->location_id,
                    'result' => 'attendu',
                ]);
            }

            $inventory->expected_count = $boxes->count();
            $inventory->save();

            $this->audit->log('inventory.created', $inventory, $actor);

            return $inventory->load(['items.box', 'location', 'structure']);
        });
    }

    public function start(User $actor, ArchiveInventory $inventory): ArchiveInventory
    {
        if ($inventory->status !== 'brouillon') {
            throw new InvalidArgumentException('Seule une campagne brouillon peut démarrer.');
        }

        $inventory->fill([
            'status' => 'en_cours',
            'started_at' => now(),
        ])->save();

        $this->audit->log('inventory.started', $inventory, $actor);

        return $inventory->fresh(['items']);
    }

    public function scan(User $actor, ArchiveInventory $inventory, string $code): ArchiveInventoryItem
    {
        if ($inventory->status !== 'en_cours') {
            throw new InvalidArgumentException('La campagne n’est pas en cours.');
        }

        $box = ArchiveBox::query()
            ->where('code', $code)
            ->orWhere('qr_token', $code)
            ->first();

        $item = null;
        if ($box) {
            $item = $inventory->items()->where('box_id', $box->id)->first();
        }

        if ($item) {
            $result = 'trouve';
            if ($box && $box->location_id && $item->expected_location_id
                && (int) $box->location_id !== (int) $item->expected_location_id) {
                $result = 'deplace';
            }

            $item->fill([
                'result' => $result,
                'scanned_code' => $code,
                'found_location_id' => $box?->location_id,
                'scanned_by' => $actor->id,
                'scanned_at' => now(),
            ])->save();
        } else {
            $item = ArchiveInventoryItem::query()->create([
                'inventory_id' => $inventory->id,
                'box_id' => $box?->id,
                'scanned_code' => $code,
                'found_location_id' => $box?->location_id,
                'result' => 'inattendu',
                'scanned_by' => $actor->id,
                'scanned_at' => now(),
            ]);
        }

        $this->refreshCounts($inventory);
        $this->audit->log('inventory.scanned', $inventory, $actor, [
            'code' => $code,
            'result' => $item->result,
        ]);

        return $item->load(['box', 'expectedLocation', 'foundLocation']);
    }

    public function close(User $actor, ArchiveInventory $inventory): ArchiveInventory
    {
        if (! in_array($inventory->status, ['en_cours', 'en_validation'], true)) {
            throw new InvalidArgumentException('Clôture impossible dans cet état.');
        }

        $inventory->items()
            ->where('result', 'attendu')
            ->whereNull('scanned_at')
            ->update(['result' => 'manquant']);

        $this->refreshCounts($inventory);

        $inventory->fill([
            'status' => 'clos',
            'closed_at' => now(),
            'validated_by' => $actor->id,
        ])->save();

        $this->audit->log('inventory.closed', $inventory, $actor);

        return $inventory->fresh(['items.box']);
    }

    private function refreshCounts(ArchiveInventory $inventory): void
    {
        $inventory->found_count = $inventory->items()->whereIn('result', ['trouve', 'deplace'])->count();
        $inventory->missing_count = $inventory->items()->where('result', 'manquant')->count();
        $inventory->unexpected_count = $inventory->items()->where('result', 'inattendu')->count();
        $inventory->save();
    }
}
