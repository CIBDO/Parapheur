<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\AbilityMapper;
use App\Support\Rbac\PermissionCatalog;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_seeder_assigns_codes_labels_and_home_routes(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = Role::findByCode('admin');
        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_system);
        $this->assertSame('/parapheur/admin', $admin->home_route);
        $this->assertTrue($admin->hasPermissionTo('admin.access'));

        $dg = Role::findByCode('dg');
        $this->assertNotNull($dg);
        $this->assertSame('Directeur Général', $dg->name);
        $this->assertSame('/parapheur/dg', $dg->home_route);

        $permission = \App\Models\Permission::query()->where('name', 'documents.act')->first();
        $this->assertNotNull($permission);
        $this->assertSame('Traiter des documents', $permission->label);
        $this->assertSame('documents', $permission->module);
    }

    public function test_ability_mapper_exposes_role_subject_for_admin(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['Administrateur']);

        $rules = app(AbilityMapper::class)->forUser($user);
        $this->assertTrue(collect($rules)->contains(
            fn ($rule) => $rule['action'] === 'manage' && $rule['subject'] === 'all'
        ));
        $this->assertSame('/parapheur/admin', $user->homeRoute());
        $this->assertTrue($user->hasRoleWithCode('admin'));
    }

    public function test_agent_gets_document_act_without_admin_bypass(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['Agent']);

        $rules = $user->abilityRules();
        $this->assertTrue(collect($rules)->contains(
            fn ($rule) => $rule['action'] === 'act' && $rule['subject'] === 'Document'
        ));
        $this->assertFalse(collect($rules)->contains(
            fn ($rule) => $rule['action'] === 'manage' && $rule['subject'] === 'Role'
        ));
        $this->assertTrue($user->hasRoleWithCode('agent'));
        $this->assertFalse($user->hasRoleWithCode('admin'));
    }

    public function test_system_role_cannot_be_deleted_via_api(): void
    {
        $this->seed(RbacSeeder::class);

        $adminUser = User::factory()->create();
        $adminUser->syncRoles(['Administrateur']);

        $role = Role::findByCode('agent');
        $this->assertNotNull($role);

        $this->actingAs($adminUser)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertStatus(422);
    }

    public function test_catalog_permission_count_is_stable(): void
    {
        $this->assertGreaterThan(100, count(PermissionCatalog::permissionNames()));
        $this->assertArrayHasKey('admin', PermissionCatalog::roles());
        $this->assertArrayHasKey('dga', PermissionCatalog::roles());
    }
}
