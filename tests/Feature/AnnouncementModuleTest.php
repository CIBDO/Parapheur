<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\AnnouncementImportance;
use App\Models\AnnouncementNotificationLog;
use App\Models\AnnouncementRecipient;
use App\Models\AnnouncementRevision;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AnnouncementCatalogSeeder;
use Database\Seeders\AnnouncementPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AnnouncementModuleTest extends TestCase
{
    use RefreshDatabase;

    private Structure $dsi;

    private Structure $dfm;

    private AnnouncementCategory $note;

    private AnnouncementCategory $service;

    private AnnouncementImportance $normal;

    private AnnouncementImportance $urgent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AnnouncementPermissionSeeder::class);
        $this->seed(AnnouncementCatalogSeeder::class);
        $this->dsi = Structure::query()->create(['code' => 'DSI', 'name' => 'Direction des systèmes d\'information', 'is_active' => true]);
        $this->dfm = Structure::query()->create(['code' => 'DFM', 'name' => 'Direction des finances', 'is_active' => true]);
        $this->note = AnnouncementCategory::query()->where('code', 'note')->firstOrFail();
        $this->service = AnnouncementCategory::query()->where('code', 'service')->firstOrFail();
        $this->normal = AnnouncementImportance::query()->where('code', 'normal')->firstOrFail();
        $this->urgent = AnnouncementImportance::query()->where('code', 'urgent')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scenario_cabinet_publishes_to_all_agents(): void
    {
        $dg = $this->user('Directeur Général', $this->dsi);
        $agent = $this->user('Agent', $this->dfm);
        Sanctum::actingAs($dg);

        $id = $this->publishDirect($dg, 'Note de la Direction générale', [
            ['mode' => 'include', 'target_type' => 'all'],
        ]);

        Sanctum::actingAs($agent);
        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Note de la Direction générale']);
        $this->getJson('/api/announcements/'.$id)->assertOk();
    }

    public function test_scenario_targeted_maintenance_and_structure_scope(): void
    {
        $author = $this->user('Directeur', $this->dsi);
        $dsiAgent = $this->user('Agent', $this->dsi);
        $dfmAgent = $this->user('Agent', $this->dfm);
        Sanctum::actingAs($author);

        $id = $this->publishDirect($author, 'Maintenance E-Tresor', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dsi->id, 'include_descendants' => false],
        ], ['category_id' => AnnouncementCategory::query()->where('code', 'maintenance')->value('id')]);

        Sanctum::actingAs($dsiAgent);
        $this->getJson('/api/announcements')->assertOk()->assertJsonFragment(['title' => 'Maintenance E-Tresor']);

        Sanctum::actingAs($dfmAgent);
        $this->getJson('/api/announcements')->assertOk()->assertJsonMissing(['title' => 'Maintenance E-Tresor']);
        $this->getJson('/api/announcements/'.$id)->assertNotFound();
    }

    public function test_scenario_direction_announcement_stays_inside_structure(): void
    {
        $author = $this->user('Directeur', $this->dfm);
        $inside = $this->user('Agent', $this->dfm);
        $outside = $this->user('Agent', $this->dsi);
        Sanctum::actingAs($author);

        $this->publishDirect($author, 'Réunion de direction DFM', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dfm->id],
        ]);

        Sanctum::actingAs($inside);
        $this->getJson('/api/announcements')->assertJsonFragment(['title' => 'Réunion de direction DFM']);
        Sanctum::actingAs($outside);
        $this->getJson('/api/announcements')->assertJsonMissing(['title' => 'Réunion de direction DFM']);
    }

    public function test_scenario_acknowledgement_is_explicit_and_blocks_dismiss(): void
    {
        $dg = $this->user('Directeur Général', $this->dsi);
        $agent = $this->user('Agent', $this->dsi);
        Sanctum::actingAs($dg);

        $id = $this->publishDirect($dg, 'Consigne urgente', [
            ['mode' => 'include', 'target_type' => 'user', 'user_id' => $agent->id],
        ], [
            'importance_id' => $this->urgent->id,
            'acknowledgement_required' => true,
            'banner_enabled' => true,
            'dismissible' => false,
        ]);

        Sanctum::actingAs($agent);
        $this->getJson('/api/announcements/'.$id)->assertOk()->assertJsonPath('acknowledged', false)->assertJsonPath('viewed', true);
        $this->postJson('/api/announcements/'.$id.'/dismiss')->assertStatus(422);
        $this->postJson('/api/announcements/'.$id.'/acknowledge')->assertOk()->assertJsonPath('acknowledged', true);
        $this->postJson('/api/announcements/'.$id.'/dismiss')->assertOk();
        $this->getJson('/api/announcements/banner')->assertOk()->assertJsonMissing(['id' => $id]);
    }

    public function test_scenario_scheduled_announcement_is_hidden_until_due_and_idempotent(): void
    {
        $dg = $this->user('Directeur Général', $this->dsi);
        $agent = $this->user('Agent', $this->dsi);
        Sanctum::actingAs($dg);
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', 'UTC'));

        $id = $this->createDraft($dg, 'Annonce programmée', [
            ['mode' => 'include', 'target_type' => 'all'],
        ], ['publish_at' => '2026-10-13 09:00:00']);
        $this->postJson('/api/announcements/'.$id.'/publish')->assertOk()->assertJsonPath('status', 'scheduled');

        Sanctum::actingAs($agent);
        $this->getJson('/api/announcements')->assertJsonMissing(['title' => 'Annonce programmée']);
        $this->getJson('/api/announcements/'.$id)->assertNotFound();

        Carbon::setTestNow(Carbon::parse('2026-10-13 09:05:00', 'UTC'));
        $this->artisan('parapheur:publish-announcements')->assertSuccessful();
        $this->artisan('parapheur:publish-announcements')->assertSuccessful();

        $this->assertSame(1, AnnouncementNotificationLog::query()->where('announcement_id', $id)->where('user_id', $agent->id)->count());
        $this->getJson('/api/announcements')->assertJsonFragment(['title' => 'Annonce programmée']);
    }

    public function test_scenario_expired_announcement_leaves_banner_but_stays_in_archives(): void
    {
        $dg = $this->user('Directeur Général', $this->dsi);
        $agent = $this->user('Agent', $this->dsi);
        Sanctum::actingAs($dg);
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', 'UTC'));

        $id = $this->publishDirect($dg, 'Information temporaire', [
            ['mode' => 'include', 'target_type' => 'all'],
        ], [
            'expires_at' => '2026-10-12 12:00:00',
            'banner_enabled' => true,
            'importance_id' => $this->urgent->id,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-12 13:00:00', 'UTC'));
        $this->artisan('parapheur:expire-announcements')->assertSuccessful();

        Sanctum::actingAs($agent);
        $this->getJson('/api/announcements/banner')->assertJsonMissing(['title' => 'Information temporaire']);
        $this->getJson('/api/announcements')->assertJsonMissing(['title' => 'Information temporaire']);
        $this->getJson('/api/announcements?archive=1')->assertJsonFragment(['title' => 'Information temporaire']);
        $this->assertSame('expired', Announcement::query()->findOrFail($id)->status->value);
    }

    public function test_scenario_confidential_url_is_hidden_even_for_technical_admin(): void
    {
        $author = $this->user('Directeur', $this->dsi);
        $target = $this->user('Agent', $this->dsi);
        $admin = $this->user('Administrateur', $this->dfm);
        Permission::findOrCreate('admin.access');
        $admin->givePermissionTo('admin.access');
        $intruder = $this->user('Agent', $this->dsi);

        Sanctum::actingAs($author);
        $id = $this->publishDirect($author, 'Note confidentielle', [
            ['mode' => 'include', 'target_type' => 'user', 'user_id' => $target->id],
        ], ['confidentiality' => 'confidential']);

        Sanctum::actingAs($target);
        $this->getJson('/api/announcements/'.$id)->assertOk();

        Sanctum::actingAs($intruder);
        $this->getJson('/api/announcements/'.$id)->assertNotFound();
        $this->getJson('/api/announcements')->assertJsonMissing(['title' => 'Note confidentielle']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/announcements/'.$id)->assertNotFound();
    }

    public function test_scenario_rejection_then_correction_and_resubmission(): void
    {
        $author = $this->user('Chef de section', $this->dsi);
        $director = $this->user('Directeur', $this->dsi);
        Sanctum::actingAs($author);

        $id = $this->createDraft($author, 'Note de service', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dsi->id],
        ], ['category_id' => $this->service->id]);
        $this->postJson('/api/announcements/'.$id.'/submit')->assertOk()->assertJsonPath('status', 'pending_validation');

        Sanctum::actingAs($director);
        $this->postJson('/api/announcements/'.$id.'/reject', ['comment' => 'Préciser la date'])->assertOk()->assertJsonPath('status', 'rejected');

        Sanctum::actingAs($author);
        $this->putJson('/api/announcements/'.$id, [
            'title' => 'Note de service corrigée',
            'body' => '<p>Version corrigée</p>',
        ])->assertOk();
        $this->postJson('/api/announcements/'.$id.'/submit')->assertOk()->assertJsonPath('status', 'pending_validation');

        Sanctum::actingAs($director);
        $this->postJson('/api/announcements/'.$id.'/approve')->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/api/announcements/'.$id.'/publish')->assertOk()->assertJsonPath('status', 'published');
    }

    public function test_scenario_ged_attachment_does_not_duplicate_document(): void
    {
        $author = $this->user('Directeur', $this->dsi);
        $type = DocumentType::query()->create(['code' => 'NOTE', 'name' => 'Note']);
        $document = Document::query()->create([
            'reference' => 'DOC-ANN-1',
            'object' => 'Note de service jointe',
            'document_type_id' => $type->id,
            'structure_id' => $this->dsi->id,
            'author_id' => $author->id,
        ]);
        Sanctum::actingAs($author);

        $id = $this->publishDirect($author, 'Annonce avec pièce GED', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dsi->id],
        ], ['document_ids' => [$document->id]]);

        $this->assertSame(1, Document::query()->count());
        $this->getJson('/api/announcements/'.$id)
            ->assertOk()
            ->assertJsonPath('attachments.0.document_id', $document->id);
    }

    public function test_scenario_statistics_respect_structure_scope(): void
    {
        $director = $this->user('Directeur', $this->dsi);
        $other = $this->user('Directeur', $this->dfm);
        $agent = $this->user('Agent', $this->dsi);
        Sanctum::actingAs($director);
        $this->publishDirect($director, 'Annonce DSI', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dsi->id],
        ]);

        $this->getJson('/api/announcements/statistics')->assertOk()->assertJsonPath('published', 1);
        $this->getJson('/api/announcements/'.Announcement::query()->first()->id.'/receipts')->assertOk()->assertJsonStructure(['total', 'rate', 'items']);

        Sanctum::actingAs($other);
        $this->getJson('/api/announcements/statistics')->assertOk()->assertJsonPath('published', 0);

        Sanctum::actingAs($agent);
        $this->getJson('/api/announcements/statistics')->assertForbidden();
    }

    public function test_scenario_substantial_change_is_historized_and_revalidated(): void
    {
        $author = $this->user('Chef de section', $this->dsi);
        $director = $this->user('Directeur', $this->dsi);
        Sanctum::actingAs($author);
        $id = $this->createDraft($author, 'Version initiale', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dsi->id],
        ], ['category_id' => $this->service->id]);
        $this->postJson('/api/announcements/'.$id.'/submit')->assertOk();
        Sanctum::actingAs($director);
        $this->postJson('/api/announcements/'.$id.'/approve')->assertOk();
        $this->postJson('/api/announcements/'.$id.'/publish')->assertOk();

        Sanctum::actingAs($author);
        $this->putJson('/api/announcements/'.$id, ['title' => 'Version rectifiée'])->assertOk()->assertJsonPath('status', 'published');
        $this->assertSame(1, AnnouncementRevision::query()->where('announcement_id', $id)->count());
        $this->assertSame(2, Announcement::query()->findOrFail($id)->current_version);
    }

    public function test_scenario_recipient_snapshot_survives_reassignment(): void
    {
        $author = $this->user('Directeur', $this->dsi);
        $agent = $this->user('Agent', $this->dsi);
        $newcomer = $this->user('Agent', $this->dfm);
        Sanctum::actingAs($author);

        $id = $this->publishDirect($author, 'Annonce figée', [
            ['mode' => 'include', 'target_type' => 'structure', 'structure_id' => $this->dsi->id],
        ]);

        $agent->update(['structure_id' => $this->dfm->id]);
        $newcomer->update(['structure_id' => $this->dsi->id]);

        $this->assertTrue(AnnouncementRecipient::query()->where('announcement_id', $id)->where('user_id', $agent->id)->exists());
        $this->assertFalse(AnnouncementRecipient::query()->where('announcement_id', $id)->where('user_id', $newcomer->id)->exists());

        Sanctum::actingAs($agent);
        $this->getJson('/api/announcements')->assertJsonFragment(['title' => 'Annonce figée']);
        Sanctum::actingAs($newcomer);
        $this->getJson('/api/announcements')->assertJsonMissing(['title' => 'Annonce figée']);
    }

    public function test_html_script_is_stripped(): void
    {
        $dg = $this->user('Directeur Général', $this->dsi);
        Sanctum::actingAs($dg);
        $id = $this->publishDirect($dg, 'Contenu riche', [
            ['mode' => 'include', 'target_type' => 'user', 'user_id' => $dg->id],
        ], ['body' => '<p>Bonjour</p><script>alert(1)</script>']);

        $body = $this->getJson('/api/announcements/'.$id)->json('body');
        $this->assertStringContainsString('Bonjour', $body);
        $this->assertStringNotContainsString('script', strtolower($body));
        $this->assertStringNotContainsString('alert', strtolower($body));
    }

    private function user(string $role, Structure $structure): User
    {
        $user = User::factory()->create([
            'structure_id' => $structure->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** @param  list<array<string, mixed>>  $audiences
     * @param  array<string, mixed>  $extra
     */
    private function createDraft(User $author, string $title, array $audiences, array $extra = []): int
    {
        $payload = array_merge([
            'title' => $title,
            'body' => '<p>'.$title.'</p>',
            'category_id' => $this->note->id,
            'importance_id' => $this->normal->id,
            'structure_id' => $author->structure_id,
            'confidentiality' => 'general',
            'audiences' => $audiences,
        ], $extra);

        return (int) $this->postJson('/api/announcements', $payload)->assertCreated()->json('id');
    }

    /** @param  list<array<string, mixed>>  $audiences
     * @param  array<string, mixed>  $extra
     */
    private function publishDirect(User $author, string $title, array $audiences, array $extra = []): int
    {
        $id = $this->createDraft($author, $title, $audiences, $extra);
        $this->postJson('/api/announcements/'.$id.'/publish')->assertOk();

        return $id;
    }
}
