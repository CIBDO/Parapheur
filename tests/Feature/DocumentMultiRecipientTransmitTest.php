<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentTransmission;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentMultiRecipientTransmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_document_can_transmit_to_multiple_recipients_in_parallel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $directeur = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $id = $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note multi destinataires',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'consultation',
            'transmit_to_ids' => [$dg->id, $directeur->id],
            'transmit_message' => 'Pour avis parallèle',
        ])->assertCreated()->json('id');

        $doc = Document::query()->findOrFail($id);
        $this->assertSame($dg->id, $doc->current_assignee_id);

        $pending = DocumentTransmission::query()
            ->where('document_id', $id)
            ->where('status', 'pending')
            ->pluck('to_user_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$dg->id, $directeur->id])->sort()->values()->all(),
            $pending
        );

        $this->actingAs($dg)->getJson("/api/parapheur/documents/{$id}")->assertOk();
        $this->actingAs($directeur)->getJson("/api/parapheur/documents/{$id}")->assertOk();
    }

    public function test_legacy_single_transmit_to_still_works(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $id = $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note un destinataire',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'validation',
            'transmit_to' => $dg->id,
        ])->assertCreated()->json('id');

        $this->assertSame(
            1,
            DocumentTransmission::query()->where('document_id', $id)->where('status', 'pending')->count()
        );
        $this->assertSame($dg->id, Document::query()->findOrFail($id)->current_assignee_id);
    }
}
