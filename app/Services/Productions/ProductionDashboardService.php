<?php

namespace App\Services\Productions;

use App\Enums\ProductionOccurrenceStatus;
use App\Models\ProductionOccurrence;
use App\Models\ProductionObligation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductionDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function myWork(User $actor): array
    {
        $base = ProductionOccurrence::query()->where('responsible_id', $actor->id);

        return [
            'expected' => (clone $base)->whereIn('status', [
                ProductionOccurrenceStatus::AProduire->value,
                ProductionOccurrenceStatus::EnPreparation->value,
                ProductionOccurrenceStatus::ACompleter->value,
                ProductionOccurrenceStatus::Rejetee->value,
            ])->count(),
            'overdue' => (clone $base)->where('deadline_at', '<', now())
                ->whereNotIn('status', [
                    ProductionOccurrenceStatus::Transmise->value,
                    ProductionOccurrenceStatus::Cloturee->value,
                    ProductionOccurrenceStatus::Annulee->value,
                ])->count(),
            'to_validate' => ProductionOccurrence::query()
                ->whereIn('status', [
                    ProductionOccurrenceStatus::Soumise->value,
                    ProductionOccurrenceStatus::EnValidation->value,
                ])
                ->whereHas('validations', fn ($q) => $q->where('validator_id', $actor->id)->where('decision', 'pending'))
                ->count(),
            'to_transmit' => (clone $base)->where('status', ProductionOccurrenceStatus::PreteATransmettre->value)->count(),
            'transmitted' => (clone $base)->where('status', ProductionOccurrenceStatus::Transmise->value)
                ->where('transmitted_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function structure(User $actor, ?int $structureId = null): array
    {
        $structureId ??= $actor->structure_id;
        $query = ProductionOccurrence::query()->when($structureId, fn ($q) => $q->where('structure_id', $structureId));

        return [
            'structure_id' => $structureId,
            'expected' => (clone $query)->whereNotIn('status', [
                ProductionOccurrenceStatus::Transmise->value,
                ProductionOccurrenceStatus::Cloturee->value,
                ProductionOccurrenceStatus::Annulee->value,
            ])->count(),
            'overdue' => (clone $query)->where('deadline_at', '<', now())
                ->whereNotIn('status', [
                    ProductionOccurrenceStatus::Transmise->value,
                    ProductionOccurrenceStatus::Cloturee->value,
                    ProductionOccurrenceStatus::Annulee->value,
                ])->count(),
            'pending_validation' => (clone $query)->whereIn('status', [
                ProductionOccurrenceStatus::Soumise->value,
                ProductionOccurrenceStatus::EnValidation->value,
            ])->count(),
            'transmitted_today' => (clone $query)->whereDate('transmitted_at', today())->count(),
            'by_status' => (clone $query)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function direction(User $actor): array
    {
        abort_unless(
            $actor->can('production.view_all')
            || $actor->can('production.manage')
            || $actor->can('dashboard.dg')
            || $actor->can('admin.access')
            || $actor->hasRole(['Directeur Général', 'DGA']),
            403
        );

        $open = [
            ProductionOccurrenceStatus::Transmise->value,
            ProductionOccurrenceStatus::Cloturee->value,
            ProductionOccurrenceStatus::Annulee->value,
        ];

        return [
            'expected_today' => ProductionOccurrence::query()
                ->whereDate('reference_date', today())
                ->whereNotIn('status', $open)
                ->count(),
            'transmitted_today' => ProductionOccurrence::query()
                ->whereDate('transmitted_at', today())
                ->count(),
            'missing_today' => ProductionOccurrence::query()
                ->whereDate('reference_date', today())
                ->whereNull('deposited_at')
                ->whereNotIn('status', $open)
                ->count(),
            'overdue' => ProductionOccurrence::query()
                ->where('deadline_at', '<', now())
                ->whereNotIn('status', $open)
                ->count(),
            'active_obligations' => ProductionObligation::query()->where('is_active', true)->count(),
            'by_structure' => ProductionOccurrence::query()
                ->select('structure_id', DB::raw('count(*) as total'))
                ->whereDate('reference_date', '>=', now()->subDays(7))
                ->groupBy('structure_id')
                ->with('structure:id,code,name')
                ->get(),
            'overdue_by_structure' => ProductionOccurrence::query()
                ->select('structure_id', DB::raw('count(*) as total'))
                ->where('deadline_at', '<', now())
                ->whereNotIn('status', $open)
                ->groupBy('structure_id')
                ->get(),
        ];
    }
}
