<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\ParapheurFolder;
use App\Models\Document;
use App\Models\DocumentTransmission;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use App\Models\Workflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParapheurWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_predefined_workflow_advances_after_validation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $directeur = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $workflow = Workflow::query()->where('code', 'CIRCUIT_STANDARD')->firstOrFail();

        $token = $agent->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/parapheur/documents', [
            'object' => 'Note circuit prédéfini',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'consultation',
            'workflow_id' => $workflow->id,
            'transmit_message' => 'Via circuit standard',
        ]);

        $response->assertCreated();
        $documentId = $response->json('id');
        $document = Document::query()->findOrFail($documentId);

        $this->assertSame(DocumentStatus::AValider, $document->status);
        $this->assertSame($directeur->id, $document->current_assignee_id);
        $this->assertSame('predefini', $document->workflowInstance?->kind);

        $this->actingAs($directeur)
            ->postJson("/api/parapheur/documents/{$documentId}/validate", ['comment' => 'OK direction'])
            ->assertOk();

        $this->assertDatabaseHas('approvals', [
            'document_id' => $documentId,
            'user_id' => $directeur->id,
            'decision' => 'valide',
        ]);

        $document->refresh();
        $this->assertNotSame($directeur->id, $document->current_assignee_id);
        $this->assertSame('predefini', $document->workflowInstance?->kind);
        $this->assertTrue(
            DocumentTransmission::query()
                ->where('document_id', $documentId)
                ->where('to_user_id', $directeur->id)
                ->where('folder', ParapheurFolder::Traites->value)
                ->where('status', 'done')
                ->exists()
        );
    }

    public function test_new_version_and_attachment_download_urls(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $token = $agent->createToken('test')->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/parapheur/documents', [
            'object' => 'Note versions',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'main_file' => UploadedFile::fake()->create('note-v1.pdf', 100, 'application/pdf'),
            'attachments' => [UploadedFile::fake()->create('annexe.pdf', 50, 'application/pdf')],
        ])->assertCreated();

        $id = $created->json('id');

        $this->withToken($token)->post("/api/parapheur/documents/{$id}/versions", [
            'file' => UploadedFile::fake()->create('note-v2.pdf', 120, 'application/pdf'),
            'change_note' => 'Corrections',
        ])->assertCreated();

        $show = $this->withToken($token)->getJson("/api/parapheur/documents/{$id}")->assertOk();
        $this->assertCount(2, $show->json('versions'));
        $this->assertNotEmpty($show->json('versions.0.download_url'));
        $this->assertNotEmpty($show->json('versions.0.preview.mode'));
        $this->assertNotEmpty($show->json('attachments.0.download_url'));
        $this->assertSame(2, Document::query()->findOrFail($id)->current_version);
    }

    public function test_admin_can_manage_workflows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/workflows')->assertOk();

        $created = $this->withToken($token)->postJson('/api/workflows', [
            'code' => 'TEST_CIRCUIT',
            'name' => 'Circuit test',
            'is_active' => true,
            'steps' => [
                ['step_order' => 1, 'name' => 'Agent', 'role_name' => 'Agent', 'expected_action' => 'consultation'],
                ['step_order' => 2, 'name' => 'DG', 'role_name' => 'Directeur Général', 'expected_action' => 'validation'],
            ],
        ])->assertCreated();

        $this->assertCount(2, $created->json('steps'));
    }
}
