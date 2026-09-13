<?php

namespace Database\Seeders;

use App\Enums\MeetingDecisionStatus;
use App\Enums\MeetingStatus;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\Meeting;
use App\Models\MeetingTemplate;
use App\Models\MeetingType;
use App\Models\Structure;
use App\Models\User;
use App\Services\MeetingService;
use Illuminate\Database\Seeder;

class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'COMITE_DIRECTION', 'name' => 'Comité de Direction', 'sort_order' => 1],
            ['code' => 'REUNION_DIRECTION', 'name' => 'Réunion de Direction', 'sort_order' => 2],
            ['code' => 'COORDINATION', 'name' => 'Réunion de coordination', 'sort_order' => 3],
            ['code' => 'TECHNIQUE', 'name' => 'Réunion technique', 'sort_order' => 4],
            ['code' => 'COMITE', 'name' => 'Comité', 'sort_order' => 5],
            ['code' => 'COMMISSION', 'name' => 'Commission', 'sort_order' => 6],
            ['code' => 'ATELIER', 'name' => 'Atelier', 'sort_order' => 7],
            ['code' => 'SEANCE_TRAVAIL', 'name' => 'Séance de travail', 'sort_order' => 8],
            ['code' => 'AUDIENCE', 'name' => 'Audience', 'sort_order' => 9],
            ['code' => 'EXTRAORDINAIRE', 'name' => 'Réunion extraordinaire', 'sort_order' => 10],
            ['code' => 'AUTRE', 'name' => 'Autre', 'sort_order' => 11],
        ];

        foreach ($types as $type) {
            MeetingType::query()->updateOrCreate(['code' => $type['code']], $type + ['is_active' => true]);
        }

        MeetingTemplate::query()->updateOrCreate(
            ['kind' => 'convocation', 'is_default' => true],
            [
                'name' => 'Convocation DGTCP',
                'body' => '<h1>CONVOCATION</h1><p>Référence : {{reference}}</p><p>Objet : {{object}}</p><p>Date : {{date}} à {{time}}</p><p>Lieu : {{location}}</p><p>Président : {{chair}}</p><p>Destinataires : {{participants}}</p><h2>Ordre du jour</h2><p>{{agenda}}</p><p>{{observations}}</p><p>Signataire : {{signatory}}</p>',
                'is_active' => true,
            ]
        );

        if (Meeting::query()->exists()) {
            return;
        }

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->first();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->first();
        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->first();
        $dfm = User::query()->where('email', 'directeur.dfm@dgtcp.local')->first();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->first();
        $structure = Structure::query()->where('code', 'DG')->first();
        $type = MeetingType::query()->where('code', 'COMITE_DIRECTION')->first();
        $service = app(MeetingService::class);

        if (! $secretariat || ! $dg || ! $type) {
            return;
        }

        $previous = $service->create($secretariat, [
            'object' => 'Comité de Direction DGTCP',
            'description' => 'Comité hebdomadaire du Directeur Général.',
            'meeting_type_id' => $type->id,
            'meeting_date' => now()->subWeek()->toDateString(),
            'meeting_time' => '10:00',
            'end_time' => '12:00',
            'location' => 'Salle du Conseil — DGTCP',
            'chair_id' => $dg->id,
            'secretary_id' => $secretariat->id,
            'structure_id' => $structure?->id,
            'participant_ids' => array_values(array_filter([$dg->id, $secretariat->id, $dsi?->id, $dfm?->id, $agent?->id])),
            'agenda_items' => [
                ['title' => 'Adoption du compte rendu précédent'],
                ['title' => 'Situation des recettes'],
                ['title' => 'État d’avancement du projet SIGRAC'],
                ['title' => 'Projet E-Tresor'],
                ['title' => 'Questions diverses'],
            ],
        ]);

        $previous->status = MeetingStatus::Cloturee;
        $previous->save();

        $admin = User::query()->where('email', 'admin@dgtcp.local')->first();
        $doc = Document::query()->first();
        if ($doc && $admin) {
            $service->attachExistingDocument($admin, $previous, $doc->id, ['kind' => 'rapport', 'agenda_label' => 'Situation des recettes']);
        }

        $d1 = $service->addDecision($dg, $previous, [
            'title' => 'Finaliser le prototype de l’E-Tresor',
            'body' => 'La DSI livre le prototype fonctionnel pour démonstration.',
            'assignee_id' => $dsi?->id,
            'due_date' => now()->addDays(5)->toDateString(),
            'priority' => 'urgente',
            'create_instruction' => true,
        ]);
        $d1->update(['status' => MeetingDecisionStatus::EnCours]);

        $d2 = $service->addDecision($dg, $previous, [
            'title' => 'Transmettre la situation hebdomadaire des recettes',
            'assignee_id' => $dfm?->id,
            'due_date' => now()->subDays(2)->toDateString(),
            'create_instruction' => true,
        ]);
        $d2->update(['status' => MeetingDecisionStatus::EnRetard]);

        $d3 = $service->addDecision($dg, $previous, [
            'title' => 'Valider le plan de communication interne SIGRAC',
            'assignee_id' => $secretariat->id,
            'due_date' => now()->subDays(10)->toDateString(),
            'create_instruction' => true,
        ]);
        $d3->update(['status' => MeetingDecisionStatus::Executee, 'executed_at' => now()->subDays(3)]);
        Instruction::query()->where('meeting_decision_id', $d3->id)->update(['status' => 'executee', 'completed_at' => now()->subDays(3)]);

        $upcoming = $service->create($secretariat, [
            'object' => 'Comité de Direction DGTCP',
            'description' => 'Séance hebdomadaire — suivi des décisions et projets structurants.',
            'meeting_type_id' => $type->id,
            'meeting_date' => now()->addDays(5)->toDateString(),
            'meeting_time' => '10:00',
            'end_time' => '12:30',
            'location' => 'Salle du Conseil — DGTCP',
            'chair_id' => $dg->id,
            'secretary_id' => $secretariat->id,
            'structure_id' => $structure?->id,
            'parent_meeting_id' => $previous->id,
            'is_recurring' => true,
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekday' => 1,
                'starts_on' => now()->toDateString(),
            ],
            'participant_ids' => array_values(array_filter([$dg->id, $secretariat->id, $dsi?->id, $dfm?->id, $agent?->id])),
            'agenda_items' => [
                ['title' => 'Adoption du compte rendu précédent'],
                ['title' => 'Situation des recettes'],
                ['title' => 'État d’avancement du projet SIGRAC'],
                ['title' => 'Projet E-Tresor'],
                ['title' => 'Questions diverses'],
            ],
        ]);

        $upcoming->status = MeetingStatus::EnPreparation;
        $upcoming->save();
    }
}