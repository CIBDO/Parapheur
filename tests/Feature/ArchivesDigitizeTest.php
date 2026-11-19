<?php

namespace Tests\Feature;

use App\Models\ArchiveFonds;
use App\Models\ArchiveSeries;
use App\Models\ArchiveTransferItem;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivesDigitizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_agent_can_digitize_batch_into_draft_transfer_with_classification_intent(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $fonds = ArchiveFonds::query()->create([
            'code' => 'F-DIG',
            'name' => 'Fonds numérisation',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $series = ArchiveSeries::query()->create([
            'fonds_id' => $fonds->id,
            'code' => 'S-DIG',
            'name' => 'Série numérisation',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->post('/api/archives/digitize/batch', [
                'structure_id' => $structure->id,
                'document_type_id' => $type->id,
                'title' => 'Lot scan test',
                'medium' => 'electronique',
                'fonds_id' => $fonds->id,
                'series_id' => $series->id,
                'files' => [
                    UploadedFile::fake()->create('piece-a.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->create('piece-b.pdf', 80, 'application/pdf'),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'brouillon')
            ->assertJsonPath('title', 'Lot scan test')
            ->assertJsonPath('source_module', 'archives');

        $transferId = (int) $response->json('id');
        $this->assertSame(2, ArchiveTransferItem::query()->where('transfer_id', $transferId)->count());

        $item = ArchiveTransferItem::query()->where('transfer_id', $transferId)->first();
        $this->assertSame($fonds->id, (int) ($item->metadata['fonds_id'] ?? 0));
        $this->assertSame($series->id, (int) ($item->metadata['series_id'] ?? 0));
    }

    public function test_digitize_dossier_mode_creates_single_document_with_attachments(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $response = $this->actingAs($agent, 'sanctum')
            ->post('/api/archives/digitize/batch', [
                'structure_id' => $structure->id,
                'document_type_id' => $type->id,
                'title' => 'Dossier scanné',
                'grouping' => 'dossier',
                'source_channel' => 'upload',
                'files' => [
                    UploadedFile::fake()->create('principal.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('annexe.pdf', 50, 'application/pdf'),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'brouillon');

        $transferId = (int) $response->json('id');
        $this->assertSame(1, ArchiveTransferItem::query()->where('transfer_id', $transferId)->count());

        $documentId = (int) ArchiveTransferItem::query()->where('transfer_id', $transferId)->value('document_id');
        $this->assertDatabaseHas('document_attachments', [
            'document_id' => $documentId,
        ]);
    }

    public function test_digitize_can_submit_and_download_bordereau(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $response = $this->actingAs($agent, 'sanctum')
            ->post('/api/archives/digitize/batch', [
                'structure_id' => $structure->id,
                'document_type_id' => $type->id,
                'title' => 'Versement avec bordereau',
                'submit_after' => true,
                'piece_meta' => json_encode([
                    ['date_start' => '2024-01-01', 'date_end' => '2024-12-31', 'qc_status' => 'ok'],
                ]),
                'files' => [
                    UploadedFile::fake()->create('scan.pdf', 90, 'application/pdf'),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'soumis');

        $transferId = (int) $response->json('id');

        $pdf = $this->actingAs($agent, 'sanctum')
            ->get("/api/archives/transfers/{$transferId}/bordereau");

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }
}
