<?php

namespace Tests\Feature;

use App\Enums\AccessScopeType;
use App\Models\Correspondence;
use App\Models\OrganizationalRoleAssignment;
use App\Models\Structure;
use App\Models\StructureType;
use App\Models\User;
use App\Services\CorrespondenceAccessService;
use App\Services\Organization\StructureBoundaryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailOrganizationalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Structure $sikasso;

    private Structure $segou;

    private Structure $serviceSikasso;

    private User $agentSikasso;

    private User $agentSegou;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $treasuryType = StructureType::query()->where('code', 'TRESORERIE')->firstOrFail();
        $serviceType = StructureType::query()->where('code', 'SERVICE')->firstOrFail();

        $this->sikasso = Structure::query()->create([
            'code' => 'TR_SIKASSO',
            'name' => 'Trésorerie Régionale de Sikasso',
            'structure_type_id' => $treasuryType->id,
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $this->segou = Structure::query()->create([
            'code' => 'TR_SEGOU',
            'name' => 'Trésorerie Régionale de Ségou',
            'structure_type_id' => $treasuryType->id,
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $this->serviceSikasso = Structure::query()->create([
            'code' => 'TR_SIKASSO_SA',
            'name' => 'Service A Sikasso',
            'parent_id' => $this->sikasso->id,
            'structure_type_id' => $serviceType->id,
            'is_active' => true,
            'is_access_boundary' => false,
        ]);

        app(StructureBoundaryService::class)->refreshAll();
        $this->sikasso->refresh();
        $this->segou->refresh();
        $this->serviceSikasso->refresh();

        $role = Role::findByName('Agent');
        $this->agentSikasso = $this->makeAgent('agent.sikasso@dgtcp.local', $this->sikasso, $role->id);
        $this->agentSegou = $this->makeAgent('agent.segou@dgtcp.local', $this->segou, $role->id);
    }

    public function test_same_role_in_two_treasuries_do_not_share_mail(): void
    {
        $sikassoMail = $this->mail($this->sikasso, $this->agentSikasso, 'Courrier Sikasso');
        $segouMail = $this->mail($this->segou, $this->agentSegou, 'Courrier Ségou');

        $this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson("/api/mail/correspondences/{$sikassoMail->id}")
            ->assertOk();

        $this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson("/api/mail/correspondences/{$segouMail->id}")
            ->assertForbidden();

        $ids = collect($this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson('/api/mail/correspondences')
            ->assertOk()
            ->json('data'))->pluck('id')->all();

        $this->assertContains($sikassoMail->id, $ids);
        $this->assertNotContains($segouMail->id, $ids);
    }

    public function test_client_cannot_register_mail_on_another_structure(): void
    {
        $this->actingAs($this->agentSikasso, 'sanctum')
            ->postJson('/api/mail/registers/incoming', [
                'subject' => 'Tentative hors périmètre',
                'correspondence_date' => now()->toDateString(),
                'received_at' => now()->toDateTimeString(),
                'medium' => 'physique',
                'structure_id' => $this->segou->id,
            ])
            ->assertForbidden();
    }

    public function test_unit_scope_does_not_include_another_service_of_the_same_treasury(): void
    {
        $role = Role::findByName('Agent');
        $serviceAgent = $this->makeAgent('agent.service.sikasso@dgtcp.local', $this->serviceSikasso, $role->id, $this->sikasso->id);
        $treasuryMail = $this->mail($this->sikasso, $this->agentSikasso, 'Courrier de la trésorerie');

        $this->actingAs($serviceAgent, 'sanctum')
            ->getJson("/api/mail/correspondences/{$treasuryMail->id}")
            ->assertForbidden();
    }

    public function test_hierarchical_scope_includes_child_units_only(): void
    {
        $role = Role::findByName('Directeur');
        $chief = User::factory()->create([
            'email' => 'chef.sikasso@dgtcp.local',
            'is_active' => true,
            'must_change_password' => false,
            'structure_id' => $this->sikasso->id,
        ]);
        $chief->syncRoles([$role->name]);

        OrganizationalRoleAssignment::query()->create([
            'user_id' => $chief->id,
            'role_id' => $role->id,
            'structure_id' => $this->sikasso->id,
            'unit_structure_id' => $this->sikasso->id,
            'scope_type' => AccessScopeType::Hierarchique,
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ]);

        $childMail = $this->mail($this->serviceSikasso, $this->agentSikasso, 'Courrier du service');
        $otherMail = $this->mail($this->segou, $this->agentSegou, 'Courrier Ségou');

        $access = app(CorrespondenceAccessService::class);
        $this->assertTrue($access->canView($chief, $childMail));
        $this->assertFalse($access->canView($chief, $otherMail));
    }

    public function test_confidential_mail_stays_hidden_without_clearance(): void
    {
        $mail = $this->mail($this->sikasso, $this->agentSikasso, 'Confidentiel Sikasso', 'confidentiel');

        $this->assertFalse(app(CorrespondenceAccessService::class)->canView($this->agentSikasso, $mail));

        $this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson("/api/mail/correspondences/{$mail->id}")
            ->assertForbidden();
    }

    public function test_switching_context_does_not_mix_structures(): void
    {
        $role = Role::findByName('Agent');
        OrganizationalRoleAssignment::query()->create([
            'user_id' => $this->agentSikasso->id,
            'role_id' => $role->id,
            'structure_id' => $this->segou->id,
            'unit_structure_id' => $this->segou->id,
            'scope_type' => AccessScopeType::Unite,
            'starts_on' => now()->toDateString(),
            'status' => 'active',
            'justification' => 'Mission temporaire',
        ]);

        $sikassoMail = $this->mail($this->sikasso, $this->agentSikasso, 'Courrier Sikasso');
        $segouMail = $this->mail($this->segou, $this->agentSegou, 'Courrier Ségou');

        $options = $this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson('/api/mail/work-context')
            ->assertOk()
            ->json('options');

        $segouAssignment = collect($options)->firstWhere('structure_id', $this->segou->id);
        $this->assertNotNull($segouAssignment);

        $this->actingAs($this->agentSikasso, 'sanctum')
            ->postJson('/api/mail/work-context', ['assignment_id' => $segouAssignment['assignment_id']])
            ->assertOk()
            ->assertJsonPath('current.structure_id', $this->segou->id);

        $this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson("/api/mail/correspondences/{$segouMail->id}")
            ->assertOk();

        $this->actingAs($this->agentSikasso, 'sanctum')
            ->getJson("/api/mail/correspondences/{$sikassoMail->id}")
            ->assertForbidden();
    }

    public function test_expired_assignment_is_ignored(): void
    {
        OrganizationalRoleAssignment::query()
            ->where('user_id', $this->agentSikasso->id)
            ->update([
                'ends_on' => now()->subDay()->toDateString(),
            ]);

        $mail = $this->mail($this->sikasso, $this->agentSikasso, 'Après expiration');

        $this->assertFalse(app(CorrespondenceAccessService::class)->canView($this->agentSikasso->fresh(), $mail));
    }

    private function makeAgent(string $email, Structure $unit, int $roleId, ?int $boundaryId = null): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'must_change_password' => false,
            'structure_id' => $unit->id,
        ]);
        $user->syncRoles(['Agent']);

        OrganizationalRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'structure_id' => $boundaryId ?? $unit->id,
            'unit_structure_id' => $unit->id,
            'scope_type' => AccessScopeType::Unite,
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ]);

        return $user;
    }

    private function mail(Structure $structure, User $author, string $subject, string $confidentiality = 'normal'): Correspondence
    {
        return Correspondence::query()->create([
            'direction' => 'entrant',
            'medium' => 'physique',
            'status' => 'enregistre',
            'subject' => $subject,
            'correspondence_date' => now()->toDateString(),
            'received_at' => now(),
            'registered_by' => $author->id,
            'structure_id' => $structure->id,
            'unit_structure_id' => $structure->id,
            'confidentiality' => $confidentiality,
        ]);
    }
}
