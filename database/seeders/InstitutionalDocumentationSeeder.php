<?php

namespace Database\Seeders;

use App\Models\DocumentDomain;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentFolderService;
use Illuminate\Database\Seeder;

class InstitutionalDocumentationSeeder extends Seeder
{
    public function run(): void
    {
        $institutionalTypes = [
            ['code' => 'LOI', 'name' => 'Loi', 'sort_order' => 10],
            ['code' => 'DECRET', 'name' => 'Décret', 'sort_order' => 11],
            ['code' => 'ARRETE', 'name' => 'Arrêté', 'sort_order' => 12],
            ['code' => 'INSTRUCTION', 'name' => 'Instruction', 'sort_order' => 13],
            ['code' => 'CIRCULAIRE', 'name' => 'Circulaire', 'sort_order' => 14],
            ['code' => 'NOTE_SERVICE', 'name' => 'Note de service', 'sort_order' => 15],
            ['code' => 'DECISION_INST', 'name' => 'Décision', 'sort_order' => 16],
            ['code' => 'PROCEDURE', 'name' => 'Procédure', 'sort_order' => 17],
            ['code' => 'MANUEL', 'name' => 'Manuel', 'sort_order' => 18],
            ['code' => 'GUIDE', 'name' => 'Guide', 'sort_order' => 19],
            ['code' => 'ETAT_FINANCIER', 'name' => 'État financier', 'sort_order' => 20],
            ['code' => 'SITUATION_TRESORERIE', 'name' => 'Situation de trésorerie', 'sort_order' => 21],
            ['code' => 'REFERENTIEL', 'name' => 'Référentiel', 'sort_order' => 22],
            ['code' => 'MODELE', 'name' => 'Modèle / formulaire', 'sort_order' => 23],
            ['code' => 'AUTRE_DOC', 'name' => 'Autre document institutionnel', 'sort_order' => 99],
        ];

        foreach ($institutionalTypes as $type) {
            DocumentType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type + ['scope' => 'institutional', 'is_active' => true]
            );
        }

        // Schémas de métadonnées configurables par type
        $schemas = [
            'CIRCULAIRE' => [
                ['key' => 'numero_interne', 'label' => 'N° interne', 'type' => 'text', 'required' => false],
                ['key' => 'signataire', 'label' => 'Signataire', 'type' => 'text', 'required' => false],
            ],
            'LOI' => [
                ['key' => 'numero_jo', 'label' => 'N° Journal officiel', 'type' => 'text', 'required' => false],
                ['key' => 'date_jo', 'label' => 'Date JO', 'type' => 'date', 'required' => false],
            ],
            'SITUATION_TRESORERIE' => [
                ['key' => 'periode', 'label' => 'Période couverte', 'type' => 'text', 'required' => true],
                ['key' => 'montant_global', 'label' => 'Montant global', 'type' => 'text', 'required' => false],
            ],
            'ETAT_FINANCIER' => [
                ['key' => 'exercice', 'label' => 'Exercice', 'type' => 'number', 'required' => true],
            ],
        ];
        foreach ($schemas as $code => $schema) {
            DocumentType::query()->where('code', $code)->update(['metadata_schema' => $schema]);
        }

        DocumentType::query()
            ->whereIn('code', ['NOTE', 'NOTE_TECH', 'RAPPORT', 'DECISION', 'CR', 'LETTRE', 'DOSSIER_REUNION', 'CONVOCATION'])
            ->update(['scope' => 'general']);

        $domains = [
            ['code' => 'COMPTA_PUB', 'name' => 'Comptabilité publique', 'sort_order' => 1],
            ['code' => 'TRESORERIE', 'name' => 'Trésorerie', 'sort_order' => 2],
            ['code' => 'FINANCES_PUB', 'name' => 'Finances publiques', 'sort_order' => 3],
            ['code' => 'ADMIN', 'name' => 'Administration', 'sort_order' => 4],
            ['code' => 'INFORMATIQUE', 'name' => 'Informatique', 'sort_order' => 5],
            ['code' => 'RH', 'name' => 'Ressources humaines', 'sort_order' => 6],
            ['code' => 'REGLEMENTATION', 'name' => 'Réglementation', 'sort_order' => 7],
            ['code' => 'CONTROLE', 'name' => 'Contrôle', 'sort_order' => 8],
            ['code' => 'ORGANISATION', 'name' => 'Organisation', 'sort_order' => 9],
            ['code' => 'AUTRE', 'name' => 'Autre domaine', 'sort_order' => 99],
        ];

        foreach ($domains as $domain) {
            DocumentDomain::query()->updateOrCreate(
                ['code' => $domain['code']],
                $domain + ['is_active' => true]
            );
        }

        $actor = User::query()->where('email', 'admin@dgtcp.local')->first()
            ?? User::query()->first();

        if ($actor) {
            app(DocumentFolderService::class)->bootstrapFromStructures($actor);
        }
    }
}
