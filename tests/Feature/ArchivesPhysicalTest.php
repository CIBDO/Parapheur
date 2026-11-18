<?php

namespace Tests\Feature;

use App\Models\ArchiveBox;
use App\Models\ArchiveStorageLocation;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivesPhysicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_location_box_and_enforce_capacity(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $room = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/locations', [
                'structure_id' => $structure->id,
                'type' => 'room',
                'code' => 'SAL01',
                'name' => 'Salle archives 1',
                'capacity_boxes' => 1,
            ])
            ->assertCreated()
            ->json();

        $this->assertSame('SAL01', $room['full_code']);

        $shelf = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/locations', [
                'structure_id' => $structure->id,
                'parent_id' => $room['id'],
                'type' => 'shelf',
                'code' => 'N01',
                'name' => 'Tablette 1',
                'capacity_boxes' => 1,
            ])
            ->assertCreated()
            ->json();

        $this->assertSame('SAL01/N01', $shelf['full_code']);

        $box = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/boxes', [
                'structure_id' => $structure->id,
                'location_id' => $shelf['id'],
                'label' => 'Boîte test',
                'linear_meters' => 0.2,
            ])
            ->assertCreated()
            ->json();

        $this->assertDatabaseHas('archive_boxes', [
            'id' => $box['id'],
            'location_id' => $shelf['id'],
            'status' => 'en_magasin',
        ]);

        $this->assertDatabaseHas('archive_movements', [
            'box_id' => $box['id'],
            'movement_type' => 'entree',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/boxes', [
                'structure_id' => $structure->id,
                'location_id' => $shelf['id'],
                'label' => 'Boîte saturante',
            ])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/boxes/'.$box['id'].'/label')
            ->assertOk()
            ->assertJsonPath('qr_payload', 'sae:box:'.$box['qr_token']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/boxes/scan/'.$box['qr_token'])
            ->assertOk()
            ->assertJsonPath('code', $box['code']);
    }

    public function test_loan_checkout_and_return_flow(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $loc = ArchiveStorageLocation::query()->create([
            'structure_id' => $structure->id,
            'type' => 'cabinet',
            'code' => 'ARM01',
            'name' => 'Armoire 1',
            'full_code' => 'ARM01',
            'status' => 'disponible',
            'capacity_boxes' => 10,
        ]);

        $box = ArchiveBox::query()->create([
            'code' => 'BX-TEST-001',
            'label' => 'Prêt test',
            'structure_id' => $structure->id,
            'location_id' => $loc->id,
            'status' => 'en_magasin',
            'physical_state' => 'bon',
            'qr_token' => 'token-test-pret-001',
        ]);

        $loan = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/loans', [
                'box_id' => $box->id,
                'purpose' => 'Consultation',
                'loan_days' => 3,
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/loans/'.$loan['id'].'/validate')
            ->assertOk()
            ->assertJsonPath('status', 'valide');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/loans/'.$loan['id'].'/checkout')
            ->assertOk()
            ->assertJsonPath('status', 'sorti');

        $this->assertDatabaseHas('archive_boxes', [
            'id' => $box->id,
            'status' => 'en_pret',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/loans/'.$loan['id'].'/return')
            ->assertOk()
            ->assertJsonPath('status', 'clos');

        $this->assertDatabaseHas('archive_boxes', [
            'id' => $box->id,
            'status' => 'en_magasin',
        ]);
    }

    public function test_inventory_scan_detects_missing_boxes(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $box = ArchiveBox::query()->create([
            'code' => 'BX-INV-001',
            'structure_id' => $structure->id,
            'status' => 'en_magasin',
            'qr_token' => 'token-inv-001',
        ]);

        $inventory = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/inventories', [
                'title' => 'Récolement DSI',
                'structure_id' => $structure->id,
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/inventories/'.$inventory['id'].'/start')
            ->assertOk()
            ->assertJsonPath('status', 'en_cours');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/inventories/'.$inventory['id'].'/scan', [
                'code' => $box->code,
            ])
            ->assertOk()
            ->assertJsonPath('result', 'trouve');

        $closed = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/inventories/'.$inventory['id'].'/close')
            ->assertOk()
            ->json();

        $this->assertSame('clos', $closed['status']);
        $this->assertGreaterThanOrEqual(1, $closed['found_count']);
    }
}
