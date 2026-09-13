<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\CalendarUnavailability;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'AUDIENCE', 'name' => 'Audience', 'default_duration_minutes' => 30, 'sort_order' => 1],
            ['code' => 'RENDEZ_VOUS_INTERNE', 'name' => 'Rendez-vous interne', 'default_duration_minutes' => 30, 'sort_order' => 2],
            ['code' => 'RENDEZ_VOUS_INSTITUTIONNEL', 'name' => 'Rendez-vous institutionnel', 'default_duration_minutes' => 45, 'sort_order' => 3],
            ['code' => 'PARTENAIRE', 'name' => 'Partenaire', 'default_duration_minutes' => 45, 'sort_order' => 4],
            ['code' => 'FOURNISSEUR', 'name' => 'Fournisseur', 'default_duration_minutes' => 30, 'sort_order' => 5],
            ['code' => 'DELEGATION', 'name' => 'Délégation', 'default_duration_minutes' => 60, 'sort_order' => 6],
            ['code' => 'VISITE_OFFICIELLE', 'name' => 'Visite officielle', 'default_duration_minutes' => 60, 'sort_order' => 7],
            ['code' => 'ENTRETIEN', 'name' => 'Entretien', 'default_duration_minutes' => 45, 'sort_order' => 8],
            ['code' => 'MISSION', 'name' => 'Mission', 'default_duration_minutes' => 60, 'sort_order' => 9],
            ['code' => 'REUNION_BILATERALE', 'name' => 'Réunion bilatérale', 'default_duration_minutes' => 60, 'sort_order' => 10],
            ['code' => 'APPEL_TELEPHONIQUE', 'name' => 'Appel téléphonique', 'default_duration_minutes' => 20, 'sort_order' => 11],
            ['code' => 'VISIOCONFERENCE', 'name' => 'Visioconférence', 'default_duration_minutes' => 60, 'sort_order' => 12],
            ['code' => 'CEREMONIE', 'name' => 'Cérémonie', 'default_duration_minutes' => 90, 'sort_order' => 13],
            ['code' => 'EVENEMENT', 'name' => 'Événement', 'default_duration_minutes' => 120, 'sort_order' => 14],
            ['code' => 'AUTRE', 'name' => 'Autre', 'default_duration_minutes' => 30, 'sort_order' => 15],
        ];

        foreach ($types as $type) {
            AppointmentType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true, 'blocks_calendar' => true]
            );
        }

        if (Appointment::query()->exists()) {
            return;
        }

        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->first();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->first();
        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->first();

        if (! $secretariat || ! $dg) {
            return;
        }

        /** @var AppointmentService $service */
        $service = app(AppointmentService::class);

        $audienceType = AppointmentType::query()->where('code', 'AUDIENCE')->first();
        $interneType = AppointmentType::query()->where('code', 'RENDEZ_VOUS_INTERNE')->first();

        $todayMorning = now()->setTime(10, 30);
        $service->create($secretariat, [
            'subject' => 'Audience Banque mondiale',
            'reason' => 'Suivi du projet de modernisation des finances publiques',
            'appointment_type_id' => $audienceType?->id,
            'start_at' => $todayMorning->toIso8601String(),
            'end_at' => $todayMorning->copy()->addMinutes(30)->toIso8601String(),
            'location' => 'Cabinet DG',
            'meeting_mode' => 'presentiel',
            'origin_type' => 'partenaire',
            'intake_channel' => 'email',
            'requester_name' => 'Mme Diallo',
            'requester_organization' => 'Banque mondiale',
            'requester_position' => 'Représentante résidente',
            'requester_email' => 'diallo@worldbank.example',
            'director_id' => $dg->id,
            'secretariat_id' => $secretariat->id,
            'priority' => 'importante',
            'context_note' => 'Point d’étape sur les livrables DSI et le planning 2026.',
            'points_to_discuss' => "1. Avancement E-Tresor\n2. Renforcement capacités\n3. Prochaines missions",
            'direct_schedule' => true,
            'participant_ids' => array_filter([$dg->id, $secretariat->id, $dsi?->id]),
        ]);

        if ($dsi) {
            $service->create($dsi, [
                'subject' => 'Point technique E-Tresor',
                'reason' => 'Arbitrage sur le module agenda',
                'appointment_type_id' => $interneType?->id,
                'requested_date' => now()->addDays(2)->toDateString(),
                'proposed_availabilities' => [
                    ['date' => now()->addDays(2)->toDateString(), 'start' => '09:00', 'end' => '09:30'],
                    ['date' => now()->addDays(2)->toDateString(), 'start' => '15:00', 'end' => '15:30'],
                ],
                'origin_type' => 'directeur',
                'director_id' => $dg->id,
                'priority' => 'urgente',
                'participant_ids' => [$dsi->id],
            ], true);
        }

        CalendarUnavailability::query()->create([
            'user_id' => $dg->id,
            'kind' => 'mission',
            'title' => 'Mission à Ségou',
            'description' => 'Déplacement officiel',
            'start_at' => now()->addDays(5)->setTime(8, 0),
            'end_at' => now()->addDays(5)->setTime(18, 0),
            'location' => 'Ségou',
            'confidentiality' => 'restreint',
            'blocks_calendar' => true,
            'created_by' => $secretariat->id,
        ]);
    }
}