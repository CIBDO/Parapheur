<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\KnowledgeArticle;
use App\Models\ServiceItem;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\TicketImpactLevel;
use App\Models\TicketNotificationPreference;
use App\Models\TicketUrgencyLevel;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TicketingSeeder::class);

        foreach ([
            'ticket.view', 'ticket.create', 'ticket.update', 'ticket.assign', 'ticket.take_charge',
            'ticket.comment', 'ticket.internal_note', 'ticket.resolve', 'ticket.close', 'ticket.reopen',
            'ticket.escalate', 'ticket.cancel', 'ticket.view_all', 'ticket.view_team', 'ticket.admin',
            'admin.access',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
    }

    private function actingAsAgent(): User
    {
        $user = User::factory()->create([
            'email' => 'tech.ticket@dgtcp.local',
            'must_change_password' => false,
        ]);
        $role = Role::findOrCreate('Technicien Test', 'web');
        $role->syncPermissions([
            'ticket.view', 'ticket.create', 'ticket.update', 'ticket.assign', 'ticket.take_charge',
            'ticket.comment', 'ticket.internal_note', 'ticket.resolve', 'ticket.close', 'ticket.reopen',
            'ticket.escalate', 'ticket.view_team', 'ticket.view_all',
        ]);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ticketPayload(array $overrides = []): array
    {
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();

        return array_merge([
            'title' => 'Ticket test',
            'description' => 'Description test',
            'service_item_id' => $item?->id,
            'custom_fields' => [
                'application' => 'SIGRAC',
            ],
        ], $overrides);
    }

    public function test_create_ticket_assigns_number_and_notifies_team(): void
    {
        Notification::fake();

        $agent = $this->actingAsAgent();
        $lead = User::factory()->create(['email' => 'lead.ticket@dgtcp.local', 'must_change_password' => false]);
        $team = SupportTeam::query()->where('code', 'APPLICATIONS')->first();
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $lead->id => ['level' => 'N2', 'is_lead' => true],
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $impact = TicketImpactLevel::query()->where('code', 'MOYEN')->first();
        $urgency = TicketUrgencyLevel::query()->where('code', 'HAUTE')->first();

        $response = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Impossible de se connecter à SIGRAC',
            'description' => 'Erreur 500 au login',
            'impact_id' => $impact?->id,
            'urgency_id' => $urgency?->id,
        ]));

        $response->assertCreated();
        $this->assertStringStartsWith('TCK/', $response->json('number'));
        $this->assertDatabaseHas('tickets', ['title' => 'Impossible de se connecter à SIGRAC']);
        $this->assertDatabaseHas('ticket_actors', [
            'ticket_id' => $response->json('id'),
            'user_id' => $agent->id,
            'role' => 'requester',
        ]);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $response->json('id'),
            'is_internal' => false,
        ]);

        Notification::assertSentTo($lead, TicketNotification::class);
    }

    public function test_create_incident_without_catalog(): void
    {
        $this->actingAsAgent();
        $typeId = \App\Models\TicketType::query()->where('code', 'INCIDENT')->value('id');

        $response = $this->postJson('/api/ticketing/tickets', [
            'title' => 'Incident classique sans catalogue',
            'description' => 'Poste bloqué',
            'ticket_type_id' => $typeId,
            'location_label' => 'Bureau 12',
        ]);

        $response->assertCreated();
        $this->assertEquals($typeId, $response->json('ticket_type_id'));
    }

    public function test_solution_propose_accept_closes_ticket(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Solution accept test',
        ]))->assertCreated()->json('id');

        $this->postJson("/api/ticketing/tickets/{$id}/assign", [
            'assignee_id' => $agent->id,
            'support_team_id' => $team->id,
        ])->assertOk();
        $this->postJson("/api/ticketing/tickets/{$id}/take-charge")->assertOk();

        $solution = $this->postJson("/api/ticketing/tickets/{$id}/solutions", [
            'content' => 'Redémarrage du service',
            'solution_type' => 'solution',
        ])->assertCreated();

        $this->postJson("/api/ticketing/tickets/{$id}/solutions/{$solution->json('id')}/accept")
            ->assertOk();

        $this->assertEquals('CLOTURE', Ticket::query()->find($id)?->status->value);
    }

    public function test_ticket_task_lifecycle(): void
    {
        $this->actingAsAgent();
        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Task test',
        ]))->assertCreated()->json('id');

        $task = $this->postJson("/api/ticketing/tickets/{$id}/tasks", [
            'title' => 'Diagnostiquer le poste',
            'content' => 'Vérifier le réseau',
        ])->assertCreated();

        $this->putJson("/api/ticketing/tickets/{$id}/tasks/{$task->json('id')}", [
            'status' => 'done',
        ])->assertOk();

        $this->assertDatabaseHas('ticket_tasks', [
            'id' => $task->json('id'),
            'status' => 'done',
        ]);
    }

    public function test_lifecycle_assign_take_charge_resolve_close(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $create = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Test cycle de vie',
            'description' => 'Cycle complet',
        ]))->assertCreated();

        $id = $create->json('id');

        $this->postJson("/api/ticketing/tickets/{$id}/assign", [
            'assignee_id' => $agent->id,
            'support_team_id' => $team->id,
        ])->assertOk();

        $this->postJson("/api/ticketing/tickets/{$id}/take-charge")->assertOk();

        $this->postJson("/api/ticketing/tickets/{$id}/resolve", [
            'summary' => 'Corrigé',
        ])->assertOk();

        $this->postJson("/api/ticketing/tickets/{$id}/close")->assertOk();

        $ticket = Ticket::query()->findOrFail($id);
        $this->assertSame(TicketStatus::Cloture, $ticket->status);
    }

    public function test_internal_note_hidden_from_requester_payload(): void
    {
        $agent = $this->actingAsAgent();
        $requester = User::factory()->create(['must_change_password' => false]);
        $role = Role::findOrCreate('Demandeur Test', 'web');
        $role->syncPermissions(['ticket.view', 'ticket.create', 'ticket.comment']);
        $requester->assignRole($role);

        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $create = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Note interne test',
        ]))->assertCreated();
        $id = $create->json('id');

        Ticket::query()->whereKey($id)->update(['requester_id' => $requester->id, 'assignee_id' => $agent->id]);

        $this->postJson("/api/ticketing/tickets/{$id}/comments", [
            'body' => 'Secret agent',
            'is_internal' => true,
        ])->assertCreated();

        Sanctum::actingAs($requester);
        $comments = $this->getJson("/api/ticketing/tickets/{$id}/comments")->assertOk()->json();
        $bodies = collect($comments['data'] ?? $comments)->pluck('body')->all();
        $this->assertNotContains('Secret agent', $bodies);
    }

    public function test_ai_suggest_returns_heuristic_suggestions_without_auto_apply(): void
    {
        $this->actingAsAgent();

        $response = $this->postJson('/api/ticketing/ai/suggest', [
            'title' => 'Impossible de se connecter à SIGRAC',
            'description' => 'Erreur login mot de passe',
        ])->assertOk();

        $response->assertJsonPath('requires_human_validation', true);
        $this->assertIsArray($response->json('suggestions'));
        $this->assertNotEmpty($response->json('suggestions'));
    }

    public function test_problem_create_and_link_ticket(): void
    {
        $agent = $this->actingAsAgent();
        foreach (['problem.view', 'problem.create', 'problem.update'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $agent->givePermissionTo(['problem.view', 'problem.create', 'problem.update']);

        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $ticketId = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Incident lié problème',
        ]))->assertCreated()->json('id');

        $problem = $this->postJson('/api/ticketing/problems', [
            'title' => 'Problème SSO récurrent',
            'description' => 'Cause racine à investiguer',
            'support_team_id' => $item?->support_team_id,
        ])->assertCreated();

        $this->assertStringStartsWith('PRB/', $problem->json('number'));
        $problemId = $problem->json('id');

        $this->postJson("/api/ticketing/problems/{$problemId}/tickets", [
            'ticket_id' => $ticketId,
        ])->assertOk();

        $show = $this->getJson("/api/ticketing/problems/{$problemId}")->assertOk();
        $linkedIds = collect($show->json('tickets'))->pluck('id')->all();
        $this->assertContains($ticketId, $linkedIds);
    }

    public function test_knowledge_article_create_publish_and_link_ticket(): void
    {
        $agent = $this->actingAsAgent();
        foreach (['knowledge.view', 'knowledge.create', 'knowledge.publish'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $agent->givePermissionTo(['knowledge.view', 'knowledge.create', 'knowledge.publish']);

        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $ticketId = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Ticket pour capitalisation KB',
        ]))->assertCreated()->json('id');

        $article = $this->postJson('/api/ticketing/knowledge', [
            'title' => 'Procédure reset SIGRAC',
            'summary' => 'Étapes de réinitialisation',
            'body' => '1. Vérifier identité\n2. Reset\n3. Notifier',
            'status' => 'DRAFT',
        ])->assertCreated();

        $articleId = $article->json('id');

        $this->postJson("/api/ticketing/knowledge/{$articleId}/publish")->assertOk();
        $this->assertSame('published', KnowledgeArticle::query()->findOrFail($articleId)->status);

        $this->postJson("/api/ticketing/knowledge/{$articleId}/tickets", [
            'ticket_id' => $ticketId,
        ])->assertOk();

        $show = $this->getJson("/api/ticketing/knowledge/{$articleId}")->assertOk();
        $linkedIds = collect($show->json('tickets'))->pluck('id')->all();
        $this->assertContains($ticketId, $linkedIds);
    }

    public function test_ticket_relations_link_and_unlink(): void
    {
        $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();

        $a = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Ticket A relations',
        ]))->assertCreated()->json('id');

        $b = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Ticket B relations',
        ]))->assertCreated()->json('id');

        $relation = $this->postJson("/api/ticketing/tickets/{$a}/relations", [
            'related_ticket_id' => $b,
            'relation_type' => 'duplicate',
        ])->assertCreated();

        $this->assertSame('duplicate', $relation->json('relation_type'));

        $list = $this->getJson("/api/ticketing/tickets/{$a}/relations")->assertOk();
        $this->assertNotEmpty($list->json('data'));

        $this->deleteJson("/api/ticketing/tickets/{$a}/relations/{$relation->json('id')}")
            ->assertOk();

        $this->assertDatabaseMissing('ticket_relations', ['id' => $relation->json('id')]);
    }

    public function test_notification_preferences_mute_mail_channel(): void
    {
        Notification::fake();

        $agent = $this->actingAsAgent();
        $this->putJson('/api/ticketing/notification-preferences', [
            'database_enabled' => true,
            'mail_enabled' => false,
            'muted_events' => [],
        ])->assertOk();

        $pref = $this->getJson('/api/ticketing/notification-preferences')->assertOk();
        $this->assertFalse($pref->json('mail_enabled'));

        $lead = User::factory()->create(['email' => 'lead.pref@dgtcp.local', 'must_change_password' => false]);
        $team = SupportTeam::query()->where('code', 'APPLICATIONS')->first();
        $team->users()->syncWithoutDetaching([
            $lead->id => ['level' => 'N2', 'is_lead' => true],
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        TicketNotificationPreference::query()->updateOrCreate(
            ['user_id' => $lead->id],
            ['database_enabled' => true, 'mail_enabled' => false, 'muted_events' => ['created']]
        );

        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Notif prefs test',
        ]))->assertCreated();

        Notification::assertNotSentTo($lead, TicketNotification::class);
    }

    public function test_sla_pauses_on_wait_and_resumes_on_take_charge(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'SLA pause test',
            'impact_id' => TicketImpactLevel::query()->where('code', 'MOYEN')->value('id'),
            'urgency_id' => TicketUrgencyLevel::query()->where('code', 'HAUTE')->value('id'),
        ]))->assertCreated()->json('id');

        $this->postJson("/api/ticketing/tickets/{$id}/assign", [
            'assignee_id' => $agent->id,
            'support_team_id' => $team->id,
        ])->assertOk();

        $this->postJson("/api/ticketing/tickets/{$id}/take-charge")->assertOk();

        $ticket = Ticket::query()->with('sla')->findOrFail($id);
        $this->assertNotNull($ticket->sla);
        $this->assertNull($ticket->sla->paused_at);

        $this->postJson("/api/ticketing/tickets/{$id}/wait", [
            'status' => 'EN_ATTENTE_DEMANDEUR',
            'comment' => 'Attente info',
        ])->assertOk();

        $ticket->refresh()->load('sla');
        $this->assertNotNull($ticket->sla->paused_at);

        $this->postJson("/api/ticketing/tickets/{$id}/take-charge")->assertOk();
        $ticket->refresh()->load('sla');
        $this->assertNull($ticket->sla->paused_at);
        $this->assertGreaterThanOrEqual(0, (int) $ticket->sla->paused_seconds);
    }

    public function test_sla_breach_check_marks_overdue_tickets(): void
    {
        Notification::fake();

        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'SLA breach test',
            'impact_id' => TicketImpactLevel::query()->where('code', 'CRITIQUE')->value('id'),
            'urgency_id' => TicketUrgencyLevel::query()->where('code', 'CRITIQUE')->value('id'),
        ]))->assertCreated()->json('id');

        $this->postJson("/api/ticketing/tickets/{$id}/assign", [
            'assignee_id' => $agent->id,
            'support_team_id' => $team->id,
        ])->assertOk();

        $ticket = Ticket::query()->with('sla')->findOrFail($id);
        $this->assertNotNull($ticket->sla);

        $ticket->sla->update([
            'response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->subMinutes(30),
            'paused_at' => null,
        ]);

        $result = app(\App\Services\Ticketing\SlaService::class)->checkBreaches();
        $this->assertGreaterThanOrEqual(1, $result['breaches']);

        $ticket->refresh()->load('sla');
        $this->assertNotNull($ticket->sla->response_breached_at);
    }

    public function test_satisfaction_after_resolve(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Satisfaction test',
        ]))->assertCreated()->json('id');

        Ticket::query()->whereKey($id)->update(['requester_id' => $agent->id]);

        $this->postJson("/api/ticketing/tickets/{$id}/assign", [
            'assignee_id' => $agent->id,
            'support_team_id' => $team->id,
        ])->assertOk();
        $this->postJson("/api/ticketing/tickets/{$id}/take-charge")->assertOk();
        $this->postJson("/api/ticketing/tickets/{$id}/resolve", [
            'summary' => 'Corrigé',
        ])->assertOk();

        $this->postJson("/api/ticketing/tickets/{$id}/satisfaction", [
            'score' => 5,
            'comment' => 'Excellent',
        ])->assertCreated();

        $this->assertDatabaseHas('ticket_satisfactions', [
            'ticket_id' => $id,
            'score' => 5,
        ]);
    }

    public function test_search_filters_by_type_and_created_dates(): void
    {
        $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $typeId = \App\Models\TicketType::query()->where('code', 'INCIDENT')->value('id');

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Recherche filtre type unique XYZ',
            'ticket_type_id' => $typeId,
        ]))->assertCreated()->json('id');

        $found = $this->getJson('/api/ticketing/tickets/search?'.http_build_query([
            'q' => 'XYZ',
            'ticket_type_id' => $typeId,
            'created_from' => now()->subDay()->toDateString(),
            'created_to' => now()->addDay()->toDateString(),
        ]))->assertOk();

        $ids = collect($found->json('data'))->pluck('id')->all();
        $this->assertContains($id, $ids);
    }

    public function test_known_error_create_for_problem(): void
    {
        $agent = $this->actingAsAgent();
        foreach (['problem.view', 'problem.create', 'problem.update'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $agent->givePermissionTo(['problem.view', 'problem.create', 'problem.update']);

        $problem = $this->postJson('/api/ticketing/problems', [
            'title' => 'Problème pour erreur connue',
            'workaround' => 'Redémarrer le service',
        ])->assertCreated();

        $ke = $this->postJson('/api/ticketing/problems/'.$problem->json('id').'/known-errors', [
            'title' => 'Timeout SSO intermittents',
            'symptoms' => 'Erreur 502 au login',
            'is_published' => true,
        ])->assertCreated();

        $this->assertTrue($ke->json('is_published'));
        $this->assertSame($problem->json('id'), $ke->json('problem_id'));

        $show = $this->getJson('/api/ticketing/problems/'.$problem->json('id'))->assertOk();
        $this->assertNotEmpty($show->json('known_errors') ?? $show->json('knownErrors'));
    }

    public function test_create_ticket_with_assignee_sets_affecte_when_no_approval(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $this->assertNotNull($item);
        $item->update(['requires_approval' => false]);

        $team = SupportTeam::query()->find($item->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $response = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Demande affectée à la création',
            'support_team_id' => $team->id,
            'assignee_id' => $agent->id,
        ]))->assertCreated();

        $this->assertSame(TicketStatus::Affecte->value, $response->json('status'));
        $this->assertSame($agent->id, $response->json('assignee_id'));
        $this->assertSame($team->id, $response->json('support_team_id'));
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $response->json('id'),
            'assignee_id' => $agent->id,
            'support_team_id' => $team->id,
            'action' => 'assign',
        ]);
        $this->assertDatabaseHas('ticket_actors', [
            'ticket_id' => $response->json('id'),
            'user_id' => $agent->id,
            'role' => 'assignee',
        ]);
    }

    public function test_create_ticket_ignores_assignee_when_approval_required(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $this->assertNotNull($item);
        $item->update(['requires_approval' => true]);

        $team = SupportTeam::query()->find($item->support_team_id);
        $this->assertNotNull($team);

        $response = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Demande soumise à validation',
            'support_team_id' => $team->id,
            'assignee_id' => $agent->id,
        ]))->assertCreated();

        $this->assertSame(TicketStatus::EnAttenteValidation->value, $response->json('status'));
        $this->assertNull($response->json('assignee_id'));
        $this->assertDatabaseMissing('ticket_assignments', [
            'ticket_id' => $response->json('id'),
            'assignee_id' => $agent->id,
        ]);
        $this->assertDatabaseHas('ticket_approvals', [
            'ticket_id' => $response->json('id'),
            'status' => 'pending',
        ]);
    }

    public function test_assign_rejects_non_team_member(): void
    {
        $agent = $this->actingAsAgent();
        $outsider = User::factory()->create(['must_change_password' => false]);
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Assign non membre',
        ]))->assertCreated()->json('id');

        $this->postJson("/api/ticketing/tickets/{$id}/assign", [
            'support_team_id' => $team->id,
            'assignee_id' => $outsider->id,
        ])->assertStatus(422);
    }

    public function test_available_actions_differ_for_requester_and_agent(): void
    {
        $agent = $this->actingAsAgent();
        $item = ServiceItem::query()->where('code', 'ASSIST_APP')->first();
        $team = SupportTeam::query()->find($item?->support_team_id);
        $this->assertNotNull($team);
        $team->users()->syncWithoutDetaching([
            $agent->id => ['level' => 'N1', 'is_lead' => false],
        ]);

        $id = $this->postJson('/api/ticketing/tickets', $this->ticketPayload([
            'title' => 'Actions disponibles test',
            'support_team_id' => $team->id,
            'assignee_id' => $agent->id,
        ]))->assertCreated()->json('id');

        $agentShow = $this->getJson("/api/ticketing/tickets/{$id}")->assertOk();
        $agentActions = $agentShow->json('available_actions') ?? [];
        $this->assertContains('resolve', $agentActions);
        $this->assertContains('assign', $agentActions);
        $this->assertContains('comment', $agentActions);

        $requester = User::factory()->create(['must_change_password' => false]);
        foreach (['ticket.view', 'ticket.comment', 'ticket.close', 'ticket.reopen'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $role = Role::findOrCreate('Demandeur Test Actions', 'web');
        $role->syncPermissions(['ticket.view', 'ticket.comment', 'ticket.close', 'ticket.reopen']);
        $requester->assignRole($role);

        Ticket::query()->whereKey($id)->update(['requester_id' => $requester->id]);

        Sanctum::actingAs($requester);
        $reqShow = $this->getJson("/api/ticketing/tickets/{$id}")->assertOk();
        $reqActions = $reqShow->json('available_actions') ?? [];
        $this->assertContains('comment', $reqActions);
        $this->assertNotContains('assign', $reqActions);
        $this->assertNotContains('escalate', $reqActions);
        $this->assertNotContains('resolve', $reqActions);
    }
}
