<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use App\Services\OnlyOffice\OnlyOfficeJwt;
use App\Services\SignedDownloadService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class IdentityAndSignedUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_url_is_one_time(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'SIGNED-001',
            'object' => 'Doc signé',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $agent->id,
            'status' => 'brouillon',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
        ]);

        Storage::disk('local')->put('documents/test.txt', 'hello');
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => 'local',
            'path' => 'documents/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
            'checksum' => hash('sha256', 'hello'),
            'is_main' => true,
            'uploaded_by' => $agent->id,
        ]);

        /** @var SignedDownloadService $signed */
        $signed = app(SignedDownloadService::class);
        $params = $signed->paramsForVersion(
            $document,
            $version->id,
            $agent,
            SignedDownloadService::PURPOSE_DOWNLOAD,
        );
        $url = URL::temporarySignedRoute(
            'documents.version.download',
            now()->addMinutes(15),
            $params,
        );

        $this->get($url)->assertOk();
        $this->get($url)->assertForbidden();
    }

    public function test_onlyoffice_jwt_accepts_previous_secret_on_decode(): void
    {
        config([
            'onlyoffice.jwt_secret' => 'new-secret-at-least-thirty-two-chars!!',
            'onlyoffice.jwt_secret_previous' => 'old-secret-at-least-thirty-two-chars!!',
        ]);

        $jwt = app(OnlyOfficeJwt::class);
        $token = \Firebase\JWT\JWT::encode(
            ['payload' => ['status' => 1], 'iat' => time(), 'exp' => time() + 60],
            'old-secret-at-least-thirty-two-chars!!',
            'HS256'
        );

        $decoded = $jwt->decode($token);
        $this->assertSame(1, $decoded['payload']['status']);
    }
}
