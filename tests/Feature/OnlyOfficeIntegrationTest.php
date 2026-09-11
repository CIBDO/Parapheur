<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use App\Services\OnlyOffice\OnlyOfficeJwt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlyOfficeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function enableOnlyOffice(): void
    {
        Config::set('onlyoffice.enabled', true);
        Config::set('onlyoffice.url', 'http://onlyoffice.test');
        Config::set('onlyoffice.app_url', 'http://app.test');
        Config::set('onlyoffice.jwt_secret', 'test-onlyoffice-secret-32bytes-min!!');
        Config::set('onlyoffice.jwt_header', 'Authorization');
        Config::set('onlyoffice.jwt_ttl', 3600);
        Config::set('onlyoffice.file_url_ttl_minutes', 120);

        // Rebind preview driver with new config
        $this->app->forgetInstance(\App\Contracts\DocumentPreviewDriver::class);
        $this->app->offsetUnset(\App\Contracts\DocumentPreviewDriver::class);
        $this->app->bind(
            \App\Contracts\DocumentPreviewDriver::class,
            \App\Services\Preview\OnlyOfficeDocumentPreviewDriver::class,
        );
    }

    private function disableOnlyOffice(): void
    {
        Config::set('onlyoffice.enabled', false);
        $this->app->forgetInstance(\App\Contracts\DocumentPreviewDriver::class);
        $this->app->offsetUnset(\App\Contracts\DocumentPreviewDriver::class);
        $this->app->bind(
            \App\Contracts\DocumentPreviewDriver::class,
            \App\Services\Preview\NativeDocumentPreviewDriver::class,
        );
    }

    /**
     * @return array{0: User, 1: Document, 2: string}
     */
    private function createDocxDocument(): array
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $token = $agent->createToken('test')->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/parapheur/documents', [
            'object' => 'Note ONLYOFFICE',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'main_file' => UploadedFile::fake()->create(
                'note.docx',
                100,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ])->assertCreated();

        $document = Document::query()->findOrFail($created->json('id'));

        return [$agent, $document, $token];
    }

    public function test_config_returns_jwt_for_office_when_enabled(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document, $token] = $this->createDocxDocument();

        $response = $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}/onlyoffice/config")
            ->assertOk();

        $response->assertJsonPath('enabled', true);
        $this->assertNotEmpty($response->json('config.token'));
        $this->assertSame('word', $response->json('config.documentType'));
        $this->assertStringContainsString('-v1', $response->json('config.document.key'));
        $this->assertStringContainsString('api.js', $response->json('api_script'));
        $this->assertTrue($response->json('config.document.permissions.edit'));
    }

    public function test_show_preview_mode_onlyoffice_for_docx_when_enabled(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document, $token] = $this->createDocxDocument();

        $show = $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}")
            ->assertOk();

        $this->assertSame('onlyoffice_editor', $show->json('versions.0.preview.mode'));
    }

    public function test_pdf_stays_native_iframe_when_onlyoffice_enabled(): void
    {
        $this->enableOnlyOffice();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $token = $agent->createToken('test')->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/parapheur/documents', [
            'object' => 'PDF natif',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'main_file' => UploadedFile::fake()->create('note.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $show = $this->withToken($token)
            ->getJson('/api/parapheur/documents/'.$created->json('id'))
            ->assertOk();

        $this->assertSame('pdf_iframe', $show->json('versions.0.preview.mode'));
    }

    public function test_office_download_when_onlyoffice_disabled(): void
    {
        $this->disableOnlyOffice();
        [$agent, $document, $token] = $this->createDocxDocument();

        $show = $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}")
            ->assertOk();

        $this->assertSame('office_download', $show->json('versions.0.preview.mode'));

        $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}/onlyoffice/config")
            ->assertStatus(503);
    }

    public function test_callback_rejects_invalid_jwt(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document] = $this->createDocxDocument();

        $this->postJson("/api/onlyoffice/callback/{$document->id}", [
            'status' => 2,
            'url' => 'http://onlyoffice.test/cache/files/out.docx',
        ], [
            'Authorization' => 'Bearer totally.invalid.token',
        ])->assertForbidden();
    }

    public function test_callback_status_2_creates_new_version(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document] = $this->createDocxDocument();

        $newContents = 'contenu-version-2-onlyoffice-'.uniqid();
        Http::fake([
            'http://onlyoffice.test/*' => Http::response($newContents, 200),
        ]);

        $jwt = app(OnlyOfficeJwt::class)->encode([
            'status' => 2,
            'url' => 'http://onlyoffice.test/cache/files/out.docx',
            'key' => $document->uuid.'-v1',
            'users' => [(string) $agent->id],
            'actions' => [['type' => 0, 'userid' => (string) $agent->id]],
        ]);

        $this->postJson("/api/onlyoffice/callback/{$document->id}", [
            'status' => 2,
            'url' => 'http://onlyoffice.test/cache/files/out.docx',
            'users' => [(string) $agent->id],
        ], [
            'Authorization' => 'Bearer '.$jwt,
        ])->assertOk()->assertJson(['error' => 0]);

        $document->refresh();
        $this->assertSame(2, $document->current_version);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 2,
            'change_note' => 'Édition collaborative ONLYOFFICE',
            'is_main' => true,
        ]);
    }

    public function test_callback_skips_version_when_checksum_identical(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document] = $this->createDocxDocument();

        $version = $document->latestVersion;
        $this->assertNotNull($version);

        // Relire le contenu stocké pour renvoyer le même checksum
        $stored = Storage::disk($version->disk)->get($version->path);
        Http::fake([
            'http://onlyoffice.test/*' => Http::response($stored, 200),
        ]);

        $jwt = app(OnlyOfficeJwt::class)->encode([
            'status' => 2,
            'url' => 'http://onlyoffice.test/cache/files/out.docx',
            'users' => [(string) $agent->id],
        ]);

        $this->postJson("/api/onlyoffice/callback/{$document->id}", [
            'status' => 2,
            'url' => 'http://onlyoffice.test/cache/files/out.docx',
        ], [
            'Authorization' => 'Bearer '.$jwt,
        ])->assertOk()->assertJson(['error' => 0]);

        $this->assertSame(1, $document->fresh()->current_version);
        $this->assertSame(1, DocumentVersion::query()->where('document_id', $document->id)->count());
    }

    public function test_archived_document_has_view_only_permissions(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document, $token] = $this->createDocxDocument();

        $document->update([
            'status' => DocumentStatus::Archive,
            'archived_at' => now(),
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}/onlyoffice/config")
            ->assertOk();

        $this->assertFalse($response->json('config.document.permissions.edit'));
        $this->assertFalse($response->json('config.document.permissions.review'));
        $this->assertFalse($response->json('config.document.permissions.comment'));
        $this->assertSame('view', $response->json('config.editorConfig.mode'));
    }

    public function test_history_and_compare_endpoints(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document, $token] = $this->createDocxDocument();

        $this->withToken($token)->post("/api/parapheur/documents/{$document->id}/versions", [
            'file' => UploadedFile::fake()->create(
                'note-v2.docx',
                120,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            'change_note' => 'Correction',
        ])->assertCreated();

        $history = $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}/onlyoffice/history")
            ->assertOk();

        $this->assertSame(2, $history->json('currentVersion'));
        $this->assertCount(2, $history->json('history'));

        $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}/onlyoffice/history/1")
            ->assertOk()
            ->assertJsonStructure(['key', 'url', 'version', 'token']);

        $this->withToken($token)
            ->getJson("/api/parapheur/documents/{$document->id}/onlyoffice/compare/1")
            ->assertOk()
            ->assertJsonStructure(['fileType', 'url', 'token']);
    }

    public function test_restore_creates_new_version_without_overwrite(): void
    {
        $this->enableOnlyOffice();
        [$agent, $document, $token] = $this->createDocxDocument();

        $this->withToken($token)->post("/api/parapheur/documents/{$document->id}/versions", [
            'file' => UploadedFile::fake()->create(
                'note-v2.docx',
                120,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            'change_note' => 'Correction',
        ])->assertCreated();

        $v1Path = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('version_number', 1)
            ->value('path');

        $this->withToken($token)
            ->postJson("/api/parapheur/documents/{$document->id}/onlyoffice/restore/1")
            ->assertCreated();

        $document->refresh();
        $this->assertSame(3, $document->current_version);
        $this->assertSame(3, DocumentVersion::query()->where('document_id', $document->id)->count());
        // V1 intacte
        $this->assertTrue(Storage::disk('local')->exists($v1Path));
    }
}
