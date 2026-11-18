<?php

namespace Database\Seeders;

use App\Models\ArchiveRetentionRule;
use Illuminate\Database\Seeder;

class ArchivesRetentionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['code' => 'TMPL-CORRESP', 'name' => 'Correspondances administratives', 'typology' => 'correspondance'],
            ['code' => 'TMPL-COMPTA', 'name' => 'Pièces comptables', 'typology' => 'comptabilite'],
            ['code' => 'TMPL-RH', 'name' => 'Dossiers de personnel', 'typology' => 'rh'],
            ['code' => 'TMPL-MARCHES', 'name' => 'Marchés publics', 'typology' => 'marches'],
            ['code' => 'TMPL-JUR', 'name' => 'Affaires juridiques', 'typology' => 'juridique'],
            ['code' => 'TMPL-PV', 'name' => 'Procès-verbaux et réunions', 'typology' => 'reunion'],
        ];

        foreach ($templates as $tpl) {
            ArchiveRetentionRule::query()->updateOrCreate(
                ['code' => $tpl['code'], 'version' => 1],
                [
                    'name' => $tpl['name'],
                    'description' => 'Modèle à compléter — aucun délai inventé.',
                    'typology' => $tpl['typology'],
                    'trigger_event' => 'cloture',
                    'current_years' => 0,
                    'intermediate_years' => 0,
                    'final_disposition' => 'conservation_definitive',
                    'legal_basis' => 'À compléter par le tableau de gestion officiel',
                    'authority' => 'DGTCP — Service des archives',
                    'is_active' => true,
                    'is_validated' => false,
                ]
            );
        }
    }
}
