<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCreateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_documents_create_cannot_create_parapheur_document(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $user = User::factory()->create([
            'structure_id' => $structure->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        // Aucun rôle / permission documents.create ni ged.create.

        Sanctum::actingAs($user);

        $this->postJson('/api/parapheur/documents', [
            'object' => 'Tentative non autorisée',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'main_file' => UploadedFile::fake()->create('note.pdf', 50, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_agent_with_documents_create_can_create_parapheur_document(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $this->assertTrue($agent->can('documents.create'));

        Sanctum::actingAs($agent);

        $this->postJson('/api/parapheur/documents', [
            'object' => 'Note autorisée',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'main_file' => UploadedFile::fake()->create('note.pdf', 50, 'application/pdf'),
        ])->assertCreated();
    }
}
