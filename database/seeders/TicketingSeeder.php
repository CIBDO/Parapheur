<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use App\Models\ServiceItem;
use App\Models\ServiceItemField;
use App\Models\SlaCalendar;
use App\Models\SlaPolicy;
use App\Models\SupportTeam;
use App\Models\TicketCategory;
use App\Models\TicketChannel;
use App\Models\TicketImpactLevel;
use App\Models\TicketPriority;
use App\Models\TicketPriorityMatrix;
use App\Models\TicketStatusRef;
use App\Models\TicketType;
use App\Models\TicketUrgencyLevel;
use App\Models\TicketingSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketingSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'INCIDENT', 'name' => 'Incident', 'sort_order' => 1],
            ['code' => 'DEMANDE_SERVICE', 'name' => 'Demande de service', 'sort_order' => 2],
            ['code' => 'ASSISTANCE', 'name' => 'Assistance', 'sort_order' => 3],
            ['code' => 'ACCES_HABILITATION', 'name' => 'Accès / Habilitation', 'sort_order' => 4],
            ['code' => 'MATERIEL', 'name' => 'Matériel', 'sort_order' => 5],
            ['code' => 'APPLICATION', 'name' => 'Application', 'sort_order' => 6],
            ['code' => 'EVOLUTION', 'name' => 'Évolution', 'sort_order' => 7],
            ['code' => 'AUTRE', 'name' => 'Autre', 'sort_order' => 8],
        ];
        foreach ($types as $row) {
            TicketType::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        $statuses = [
            ['code' => 'NOUVEAU', 'name' => 'Nouveau', 'sort_order' => 1],
            ['code' => 'A_QUALIFIER', 'name' => 'À qualifier', 'sort_order' => 2],
            ['code' => 'AFFECTE', 'name' => 'Affecté', 'sort_order' => 3],
            ['code' => 'PRIS_EN_CHARGE', 'name' => 'Pris en charge', 'sort_order' => 4],
            ['code' => 'EN_COURS', 'name' => 'En cours', 'sort_order' => 5],
            ['code' => 'EN_ATTENTE_DEMANDEUR', 'name' => 'En attente demandeur', 'pauses_sla' => true, 'sort_order' => 6],
            ['code' => 'EN_ATTENTE_TIERS', 'name' => 'En attente tiers', 'pauses_sla' => true, 'sort_order' => 7],
            ['code' => 'ESCALADE', 'name' => 'Escaladé', 'sort_order' => 8],
            ['code' => 'EN_ATTENTE_VALIDATION', 'name' => 'En attente validation', 'pauses_sla' => true, 'sort_order' => 9],
            ['code' => 'RESOLU', 'name' => 'Résolu', 'sort_order' => 10],
            ['code' => 'A_VALIDER', 'name' => 'À valider', 'sort_order' => 11],
            ['code' => 'REOUVERT', 'name' => 'Réouvert', 'sort_order' => 12],
            ['code' => 'CLOTURE', 'name' => 'Clôturé', 'sort_order' => 13],
            ['code' => 'ANNULE', 'name' => 'Annulé', 'sort_order' => 14],
        ];
        foreach ($statuses as $row) {
            TicketStatusRef::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'pauses_sla' => $row['pauses_sla'] ?? false]
            );
        }

        $priorities = [
            ['code' => 'P1_CRITIQUE', 'name' => 'P1 Critique', 'level' => 1, 'sort_order' => 1],
            ['code' => 'P2_HAUTE', 'name' => 'P2 Haute', 'level' => 2, 'sort_order' => 2],
            ['code' => 'P3_NORMALE', 'name' => 'P3 Normale', 'level' => 3, 'sort_order' => 3],
            ['code' => 'P4_FAIBLE', 'name' => 'P4 Faible', 'level' => 4, 'sort_order' => 4],
        ];
        foreach ($priorities as $row) {
            TicketPriority::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        $impacts = [
            ['code' => 'FAIBLE', 'name' => 'Faible', 'level' => 1, 'sort_order' => 1],
            ['code' => 'MOYEN', 'name' => 'Moyen', 'level' => 2, 'sort_order' => 2],
            ['code' => 'ELEVE', 'name' => 'Élevé', 'level' => 3, 'sort_order' => 3],
            ['code' => 'CRITIQUE', 'name' => 'Critique', 'level' => 4, 'sort_order' => 4],
        ];
        foreach ($impacts as $row) {
            TicketImpactLevel::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        $urgencies = [
            ['code' => 'FAIBLE', 'name' => 'Faible', 'level' => 1, 'sort_order' => 1],
            ['code' => 'MOYENNE', 'name' => 'Moyenne', 'level' => 2, 'sort_order' => 2],
            ['code' => 'HAUTE', 'name' => 'Haute', 'level' => 3, 'sort_order' => 3],
            ['code' => 'CRITIQUE', 'name' => 'Critique', 'level' => 4, 'sort_order' => 4],
        ];
        foreach ($urgencies as $row) {
            TicketUrgencyLevel::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        // Matrice Impact × Urgence → Priorité (lignes = impact, colonnes = urgence)
        $matrix = [
            'FAIBLE' => ['FAIBLE' => 'P4_FAIBLE', 'MOYENNE' => 'P4_FAIBLE', 'HAUTE' => 'P3_NORMALE', 'CRITIQUE' => 'P2_HAUTE'],
            'MOYEN' => ['FAIBLE' => 'P4_FAIBLE', 'MOYENNE' => 'P3_NORMALE', 'HAUTE' => 'P2_HAUTE', 'CRITIQUE' => 'P2_HAUTE'],
            'ELEVE' => ['FAIBLE' => 'P3_NORMALE', 'MOYENNE' => 'P2_HAUTE', 'HAUTE' => 'P2_HAUTE', 'CRITIQUE' => 'P1_CRITIQUE'],
            'CRITIQUE' => ['FAIBLE' => 'P2_HAUTE', 'MOYENNE' => 'P2_HAUTE', 'HAUTE' => 'P1_CRITIQUE', 'CRITIQUE' => 'P1_CRITIQUE'],
        ];
        foreach ($matrix as $impactCode => $urgMap) {
            $impact = TicketImpactLevel::query()->where('code', $impactCode)->first();
            foreach ($urgMap as $urgCode => $prioCode) {
                $urgency = TicketUrgencyLevel::query()->where('code', $urgCode)->first();
                $priority = TicketPriority::query()->where('code', $prioCode)->first();
                if ($impact && $urgency && $priority) {
                    TicketPriorityMatrix::query()->updateOrCreate(
                        ['impact_id' => $impact->id, 'urgency_id' => $urgency->id],
                        ['priority_id' => $priority->id]
                    );
                }
            }
        }

        foreach ([
            ['code' => 'PORTAIL', 'name' => 'Portail', 'sort_order' => 1],
            ['code' => 'AGENT_SUPPORT', 'name' => 'Agent support', 'sort_order' => 2],
            ['code' => 'TELEPHONE', 'name' => 'Téléphone', 'sort_order' => 3],
            ['code' => 'EMAIL', 'name' => 'E-mail', 'sort_order' => 4],
            ['code' => 'AUTRE', 'name' => 'Autre', 'sort_order' => 5],
        ] as $row) {
            TicketChannel::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        $informatique = TicketCategory::query()->updateOrCreate(
            ['code' => 'INFORMATIQUE'],
            ['name' => 'Informatique', 'is_active' => true, 'sort_order' => 1]
        );
        foreach ([
            ['code' => 'APPS', 'name' => 'Applications', 'sort_order' => 1],
            ['code' => 'RESEAU', 'name' => 'Réseau', 'sort_order' => 2],
            ['code' => 'MESSAGERIE', 'name' => 'Messagerie', 'sort_order' => 3],
            ['code' => 'MATERIEL_CAT', 'name' => 'Matériel', 'sort_order' => 4],
            ['code' => 'ACCES', 'name' => 'Accès / habilitations', 'sort_order' => 5],
        ] as $row) {
            TicketCategory::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['parent_id' => $informatique->id, 'is_active' => true]
            );
        }

        $calendar = SlaCalendar::query()->updateOrCreate(
            ['code' => 'OUVRE_STD'],
            [
                'name' => 'Horaires ouvrés standard',
                'weekdays' => [1, 2, 3, 4, 5],
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'is_24_7' => false,
                'is_active' => true,
            ]
        );

        $slaByPriority = [
            'P1_CRITIQUE' => [15, 240],
            'P2_HAUTE' => [60, 480],
            'P3_NORMALE' => [240, 960],
            'P4_FAIBLE' => [480, 2400],
        ];
        foreach ($slaByPriority as $code => [$response, $resolution]) {
            $priority = TicketPriority::query()->where('code', $code)->first();
            if (! $priority) {
                continue;
            }
            SlaPolicy::query()->updateOrCreate(
                ['code' => 'SLA_'.$code],
                [
                    'name' => 'SLA '.$priority->name,
                    'priority_id' => $priority->id,
                    'sla_calendar_id' => $calendar->id,
                    'response_minutes' => $response,
                    'resolution_minutes' => $resolution,
                    'warning_percent' => 80,
                    'is_active' => true,
                ]
            );
        }

        $teams = [
            ['code' => 'SUPPORT_USERS', 'name' => 'Support utilisateurs'],
            ['code' => 'APPLICATIONS', 'name' => 'Applications'],
            ['code' => 'INFRA', 'name' => 'Infrastructure'],
            ['code' => 'RESEAU', 'name' => 'Réseau'],
        ];
        foreach ($teams as $row) {
            SupportTeam::query()->updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        $admin = User::query()->where('email', 'admin@dgtcp.local')->first();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->first();
        $appsTeam = SupportTeam::query()->where('code', 'APPLICATIONS')->first();
        $supportTeam = SupportTeam::query()->where('code', 'SUPPORT_USERS')->first();
        if ($appsTeam && $admin) {
            $appsTeam->users()->syncWithoutDetaching([
                $admin->id => ['level' => 'N3', 'is_lead' => true],
            ]);
        }
        if ($appsTeam && $agent) {
            $appsTeam->users()->syncWithoutDetaching([
                $agent->id => ['level' => 'N2', 'is_lead' => false],
            ]);
        }
        if ($supportTeam && $agent) {
            $supportTeam->users()->syncWithoutDetaching([
                $agent->id => ['level' => 'N1', 'is_lead' => false],
            ]);
        }
        if ($supportTeam && $admin) {
            $supportTeam->users()->syncWithoutDetaching([
                $admin->id => ['level' => 'N2', 'is_lead' => true],
            ]);
        }

        $catalog = ServiceCatalog::query()->updateOrCreate(
            ['code' => 'IT'],
            ['name' => 'Informatique', 'description' => 'Catalogue DSI', 'is_active' => true, 'sort_order' => 1]
        );

        $defaultSla = SlaPolicy::query()->where('code', 'SLA_P3_NORMALE')->first();
        $defaultPrio = TicketPriority::query()->where('code', 'P3_NORMALE')->first();
        $incidentType = TicketType::query()->where('code', 'INCIDENT')->first();
        $appsCat = TicketCategory::query()->where('code', 'APPS')->first();

        $item = ServiceItem::query()->updateOrCreate(
            ['code' => 'ASSIST_APP'],
            [
                'service_catalog_id' => $catalog->id,
                'ticket_type_id' => $incidentType?->id,
                'ticket_category_id' => $appsCat?->id,
                'support_team_id' => $appsTeam?->id,
                'sla_policy_id' => $defaultSla?->id,
                'default_priority_id' => $defaultPrio?->id,
                'name' => 'Assistance applicative',
                'description' => 'Signalement d’anomalie ou aide sur une application métier',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        foreach ([
            ['code' => 'application', 'label' => 'Application concernée', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
            ['code' => 'module', 'label' => 'Module', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 2],
            ['code' => 'error_message', 'label' => 'Message d’erreur', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 3],
            ['code' => 'since_when', 'label' => 'Depuis quand ?', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
        ] as $field) {
            ServiceItemField::query()->updateOrCreate(
                ['service_item_id' => $item->id, 'code' => $field['code']],
                $field
            );
        }

        ServiceItem::query()->updateOrCreate(
            ['code' => 'RESET_ACCESS'],
            [
                'service_catalog_id' => $catalog->id,
                'ticket_type_id' => TicketType::query()->where('code', 'ACCES_HABILITATION')->value('id'),
                'ticket_category_id' => TicketCategory::query()->where('code', 'ACCES')->value('id'),
                'support_team_id' => $supportTeam?->id,
                'sla_policy_id' => $defaultSla?->id,
                'default_priority_id' => $defaultPrio?->id,
                'name' => 'Réinitialisation d’accès',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        TicketingSetting::query()->updateOrCreate(
            ['key' => 'auto_close_days'],
            ['value' => ['days' => 5]]
        );

        $kbCat = \App\Models\KnowledgeCategory::query()->updateOrCreate(
            ['code' => 'FAQ_IT'],
            ['name' => 'FAQ Informatique', 'is_active' => true]
        );

        \App\Models\KnowledgeArticle::query()->updateOrCreate(
            ['slug' => 'reinitialiser-mot-de-passe'],
            [
                'knowledge_category_id' => $kbCat->id,
                'title' => 'Réinitialiser un mot de passe métier',
                'summary' => 'Procédure standard de reset d’accès utilisateur',
                'body' => "1. Vérifier l’identité du demandeur\n2. Réinitialiser via l’annuaire\n3. Communiquer temporairement via canal sécurisé\n4. Exiger le changement à la première connexion",
                'status' => 'published',
                'author_id' => $admin?->id,
                'published_at' => now(),
            ]
        );

        \App\Models\KnowledgeArticle::query()->updateOrCreate(
            ['slug' => 'panne-sigrac-login'],
            [
                'knowledge_category_id' => $kbCat->id,
                'title' => 'SIGRAC — impossible de se connecter',
                'summary' => 'Contournements connus pour les erreurs de login SIGRAC',
                'body' => "Vérifier le certificat, vider le cache navigateur, tester un autre navigateur, puis escalader à l’équipe Applications si persistant.",
                'status' => 'published',
                'author_id' => $admin?->id,
                'published_at' => now(),
            ]
        );

        if ($appsTeam) {
            \App\Models\Problem::query()->updateOrCreate(
                ['number' => 'PRB/SEED/000001'],
                [
                    'title' => 'Instabilité récurrente authentification applicative',
                    'description' => 'Plusieurs incidents liés aux timeouts SSO / annuaire.',
                    'status' => 'OPEN',
                    'workaround' => 'Relancer le service d’auth et basculer sur le nœud secondaire.',
                    'owner_id' => $admin?->id,
                    'support_team_id' => $appsTeam->id,
                ]
            );
        }

        foreach ([
            ['code' => 'SIGRAC', 'name' => 'SIGRAC', 'description' => 'Système de gestion des recettes'],
            ['code' => 'PARAPHEUR', 'name' => 'Parapheur électronique', 'description' => 'Circuit de validation documentaire'],
            ['code' => 'MESSAGERIE', 'name' => 'Messagerie institutionnelle', 'description' => 'Boîtes et calendriers'],
        ] as $app) {
            \App\Models\Application::query()->updateOrCreate(
                ['code' => $app['code']],
                $app + ['is_active' => true]
            );
        }

        \App\Models\Asset::query()->updateOrCreate(
            ['inventory_number' => 'INV-PC-0001'],
            [
                'name' => 'Poste agent DSI',
                'location' => 'Bâtiment principal — DSI',
                'status' => 'active',
            ]
        );
    }
}
