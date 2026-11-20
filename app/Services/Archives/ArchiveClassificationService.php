<?php

namespace App\Services\Archives;

use App\Models\ArchiveClassificationNode;
use App\Models\ArchiveFonds;
use App\Models\ArchiveRecord;
use App\Models\ArchiveSeries;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArchiveClassificationService
{
    public function __construct(
        private readonly ArchiveAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFonds(User $actor, array $data): ArchiveFonds
    {
        $fonds = ArchiveFonds::query()->create([
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'structure_id' => $data['structure_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->audit->log('fonds.created', $fonds, $actor);

        return $fonds;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSeries(User $actor, array $data): ArchiveSeries
    {
        $series = ArchiveSeries::query()->create([
            'fonds_id' => (int) $data['fonds_id'],
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->audit->log('series.created', $series, $actor);

        return $series->load('fonds');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFonds(User $actor, ArchiveFonds $fonds, array $data): ArchiveFonds
    {
        $fonds->fill([
            'code' => array_key_exists('code', $data) ? strtoupper(trim((string) $data['code'])) : $fonds->code,
            'name' => $data['name'] ?? $fonds->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $fonds->description,
            'structure_id' => array_key_exists('structure_id', $data) ? $data['structure_id'] : $fonds->structure_id,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $fonds->is_active,
            'sort_order' => $data['sort_order'] ?? $fonds->sort_order,
        ])->save();

        $this->audit->log('fonds.updated', $fonds, $actor);

        return $fonds->fresh(['series', 'structure']);
    }

    public function deleteFonds(User $actor, ArchiveFonds $fonds): void
    {
        if ($fonds->series()->exists() || $fonds->records()->exists()
            || ArchiveClassificationNode::query()->where('fonds_id', $fonds->id)->exists()) {
            throw new InvalidArgumentException('Impossible de supprimer un fonds lié à des séries, nœuds ou archives.');
        }

        $this->audit->log('fonds.deleted', $fonds, $actor);
        $fonds->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSeries(User $actor, ArchiveSeries $series, array $data): ArchiveSeries
    {
        $series->fill([
            'fonds_id' => array_key_exists('fonds_id', $data) ? (int) $data['fonds_id'] : $series->fonds_id,
            'code' => array_key_exists('code', $data) ? strtoupper(trim((string) $data['code'])) : $series->code,
            'name' => $data['name'] ?? $series->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $series->description,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $series->is_active,
            'sort_order' => $data['sort_order'] ?? $series->sort_order,
        ])->save();

        $this->audit->log('series.updated', $series, $actor);

        return $series->fresh(['fonds']);
    }

    public function deleteSeries(User $actor, ArchiveSeries $series): void
    {
        if ($series->records()->exists()
            || ArchiveClassificationNode::query()->where('series_id', $series->id)->exists()) {
            throw new InvalidArgumentException('Impossible de supprimer une série liée à des nœuds ou archives.');
        }

        $this->audit->log('series.deleted', $series, $actor);
        $series->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createNode(User $actor, array $data): ArchiveClassificationNode
    {
        return DB::transaction(function () use ($actor, $data) {
            $parent = null;
            $depth = 0;
            $pathPrefix = '';

            if (! empty($data['parent_id'])) {
                $parent = ArchiveClassificationNode::query()->findOrFail((int) $data['parent_id']);
                $depth = $parent->depth + 1;
                $pathPrefix = rtrim((string) $parent->path, '/');
            }

            $code = strtoupper(trim((string) $data['code']));
            $path = $pathPrefix === '' ? $code : $pathPrefix.'/'.$code;

            $node = ArchiveClassificationNode::query()->create([
                'parent_id' => $parent?->id,
                'fonds_id' => $data['fonds_id'] ?? $parent?->fonds_id,
                'series_id' => $data['series_id'] ?? $parent?->series_id,
                'structure_id' => $data['structure_id'] ?? $parent?->structure_id,
                'version' => $data['version'] ?? ($parent?->version ?? 1),
                'level' => $data['level'] ?? 'serie',
                'code' => $code,
                'name' => $data['name'],
                'path' => $path,
                'depth' => $depth,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->audit->log('classification.created', $node, $actor);

            return $node->load(['parent', 'fonds', 'series']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateNode(User $actor, ArchiveClassificationNode $node, array $data): ArchiveClassificationNode
    {
        $node->fill([
            'name' => $data['name'] ?? $node->name,
            'level' => $data['level'] ?? $node->level,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $node->is_active,
            'sort_order' => $data['sort_order'] ?? $node->sort_order,
            'fonds_id' => $data['fonds_id'] ?? $node->fonds_id,
            'series_id' => $data['series_id'] ?? $node->series_id,
        ])->save();

        $this->audit->log('classification.updated', $node, $actor);

        return $node->fresh(['parent', 'fonds', 'series']);
    }

    public function deleteNode(User $actor, ArchiveClassificationNode $node): void
    {
        if ($node->children()->exists() || $node->records()->exists()) {
            throw new InvalidArgumentException('Impossible de supprimer un nœud ayant des enfants ou des archives.');
        }

        $this->audit->log('classification.deleted', $node, $actor);
        $node->delete();
    }

    public function classifyRecord(
        User $actor,
        ArchiveRecord $record,
        ?int $nodeId,
        ?int $fondsId = null,
        ?int $seriesId = null,
    ): ArchiveRecord {
        $node = $nodeId ? ArchiveClassificationNode::query()->findOrFail($nodeId) : null;

        $record->fill([
            'classification_node_id' => $node?->id,
            'fonds_id' => $fondsId ?? $node?->fonds_id ?? $record->fonds_id,
            'series_id' => $seriesId ?? $node?->series_id ?? $record->series_id,
        ])->save();

        $this->audit->log('record.classified', $record, $actor, [
            'classification_node_id' => $record->classification_node_id,
            'fonds_id' => $record->fonds_id,
            'series_id' => $record->series_id,
        ]);

        return $record->fresh(['classificationNode', 'fonds', 'series', 'structure', 'document']);
    }
}
