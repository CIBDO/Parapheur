<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParapheurSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_search_filters_documents_by_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->where('code', 'NOTE')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $doc1 = Document::query()->create([
            'reference' => 'SRCH-001',
            'object' => 'Objet de test A',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $agent->id,
            'status' => DocumentStatus::AValider->value,
            'priority' => DocumentPriority::Urgente->value,
            'confidentiality' => DocumentConfidentiality::Restreint->value,
            'expected_action' => 'validation',
            'document_date' => '2026-09-01',
            'due_date' => '2026-09-10',
            'keywords' => ['k-alpha', 'k-beta'],
            'submitted_at' => now(),
            'current_version' => 1,
        ]);

        $doc2 = Document::query()->create([
            'reference' => 'SRCH-002',
            'object' => 'Objet de test B',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $agent->id,
            'status' => DocumentStatus::ACorriger->value,
            'priority' => DocumentPriority::Normale->value,
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'expected_action' => 'observations',
            'document_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'keywords' => ['k-gamma'],
            'submitted_at' => now(),
            'current_version' => 1,
        ]);

        $token = $agent->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(
            '/api/parapheur/documents?'.
            http_build_query([
                'reference' => 'SRCH-001',
                'status' => DocumentStatus::AValider->value,
                'priority' => DocumentPriority::Urgente->value,
                'confidentiality' => DocumentConfidentiality::Restreint->value,
                'keywords' => 'k-alpha',
                'document_date_from' => '2026-09-01',
                'document_date_to' => '2026-09-30',
            ])
        );

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame($doc1->id, $data[0]['id']);
    }
}

