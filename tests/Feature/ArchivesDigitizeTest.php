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
}
