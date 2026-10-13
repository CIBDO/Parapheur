<?php

namespace Database\Seeders;

use App\Models\AnnouncementCategory;
use App\Models\AnnouncementImportance;
use Illuminate\Database\Seeder;

class AnnouncementCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['code' => 'information', 'name' => 'Information', 'color' => 'info', 'rank' => 1, 'banner_default' => false],
            ['code' => 'normal', 'name' => 'Normal', 'color' => 'primary', 'rank' => 2, 'banner_default' => false],
            ['code' => 'important', 'name' => 'Important', 'color' => 'warning', 'rank' => 3, 'banner_default' => true],
            ['code' => 'urgent', 'name' => 'Urgent', 'color' => 'error', 'rank' => 4, 'banner_default' => true],
            ['code' => 'critical', 'name' => 'Critique', 'color' => 'error', 'rank' => 5, 'banner_default' => true],
        ];

        foreach ($levels as $level) {
            AnnouncementImportance::query()->updateOrCreate(['code' => $level['code']], $level + ['is_active' => true]);
        }

        $normal = AnnouncementImportance::query()->where('code', 'normal')->first();
        $important = AnnouncementImportance::query()->where('code', 'important')->first();
        $urgent = AnnouncementImportance::query()->where('code', 'urgent')->first();

        $categories = [
            ['code' => 'dg', 'name' => 'Annonce de la Direction Générale', 'validation_circuit' => 'institutional', 'revalidate_on_change' => true, 'importance' => $important],
            ['code' => 'admin', 'name' => 'Information administrative', 'validation_circuit' => 'structure', 'revalidate_on_change' => true, 'importance' => $normal],
            ['code' => 'note', 'name' => 'Note d\'information', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'importance' => $normal],
            ['code' => 'service', 'name' => 'Communication de service', 'validation_circuit' => 'structure', 'revalidate_on_change' => true, 'importance' => $normal],
            ['code' => 'alerte', 'name' => 'Alerte opérationnelle', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'acknowledgement_required' => true, 'importance' => $urgent],
            ['code' => 'maintenance', 'name' => 'Maintenance informatique', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'importance' => $important],
            ['code' => 'indispo', 'name' => 'Indisponibilité d\'une application', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'importance' => $important],
            ['code' => 'reunion', 'name' => 'Réunion ou événement', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'importance' => $normal],
            ['code' => 'formation', 'name' => 'Formation', 'validation_circuit' => 'structure', 'revalidate_on_change' => true, 'importance' => $normal],
            ['code' => 'orga', 'name' => 'Changement organisationnel', 'validation_circuit' => 'institutional', 'revalidate_on_change' => true, 'importance' => $important],
            ['code' => 'rh', 'name' => 'Information RH non confidentielle', 'validation_circuit' => 'structure', 'revalidate_on_change' => true, 'importance' => $normal],
            ['code' => 'securite', 'name' => 'Consigne de sécurité', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'acknowledgement_required' => true, 'importance' => $urgent],
            ['code' => 'rappel', 'name' => 'Rappel institutionnel', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'importance' => $normal],
            ['code' => 'autre', 'name' => 'Autre', 'validation_circuit' => 'direct', 'revalidate_on_change' => false, 'importance' => $normal],
        ];

        foreach ($categories as $index => $category) {
            $importance = $category['importance'] ?? $normal;
            unset($category['importance']);
            AnnouncementCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category + [
                    'default_importance_id' => $importance?->id,
                    'default_confidentiality' => 'general',
                    'notify_on_publish' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                    'acknowledgement_required' => $category['acknowledgement_required'] ?? false,
                ]
            );
        }
    }
}
