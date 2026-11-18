<?php

namespace Database\Seeders;

use App\Models\ArchiveClassificationNode;
use App\Models\ArchiveFonds;
use App\Models\ArchiveSeries;
use App\Models\Structure;
use Illuminate\Database\Seeder;

class ArchivesClassificationSeeder extends Seeder
{
    public function run(): void
    {
        $dgtcp = Structure::query()->where('code', 'DGTCP')->first()
            ?? Structure::query()->orderBy('id')->first();

        if (! $dgtcp) {
            return;
        }

        $fonds = ArchiveFonds::query()->updateOrCreate(
            ['code' => 'DGTCP'],
            [
                'name' => 'Fonds Direction Générale du Trésor et de la Comptabilité Publique',
                'description' => 'Fonds institutionnel principal — plan de classement archivistique E-Trésor',
                'structure_id' => $dgtcp->id,
                'is_active' => true,
            ]
        );

        $series = ArchiveSeries::query()->updateOrCreate(
            ['fonds_id' => $fonds->id, 'code' => 'FONC'],
            [
                'name' => 'Séries fonctionnelles',
                'description' => 'Classement par fonctions et activités',
                'is_active' => true,
            ]
        );

        $domains = [
            ['code' => 'GOUV', 'name' => 'Gouvernance'],
            ['code' => 'ADM', 'name' => 'Administration générale'],
            ['code' => 'RH', 'name' => 'Ressources humaines'],
            ['code' => 'COMPTA', 'name' => 'Comptabilité publique'],
            ['code' => 'TRESOR', 'name' => 'Trésorerie'],
            ['code' => 'RECETTES', 'name' => 'Recettes'],
            ['code' => 'DEPENSES', 'name' => 'Dépenses'],
            ['code' => 'BANQUE', 'name' => 'Opérations bancaires'],
            ['code' => 'CONTROLE', 'name' => 'Contrôle et inspection'],
            ['code' => 'JUR', 'name' => 'Affaires juridiques'],
            ['code' => 'MARCHES', 'name' => 'Marchés et contrats'],
            ['code' => 'SI', 'name' => 'Systèmes d’information'],
            ['code' => 'PATRIMOINE', 'name' => 'Patrimoine'],
            ['code' => 'DOC-REG', 'name' => 'Documentation réglementaire'],
            ['code' => 'CORRESP', 'name' => 'Correspondances'],
            ['code' => 'AUTRES', 'name' => 'Autres domaines configurables'],
        ];

        foreach ($domains as $index => $domain) {
            $path = 'DGTCP/FONC/'.$domain['code'];
            ArchiveClassificationNode::query()->updateOrCreate(
                ['path' => $path, 'version' => 1],
                [
                    'parent_id' => null,
                    'fonds_id' => $fonds->id,
                    'series_id' => $series->id,
                    'structure_id' => $dgtcp->id,
                    'code' => $domain['code'],
                    'name' => $domain['name'],
                    'level' => 'domaine',
                    'depth' => 1,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
