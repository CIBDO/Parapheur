<?php

namespace Tests\Feature;

use App\Models\Correspondence;
use App\Models\Structure;
use App\Models\StructurePosition;
use App\Models\User;
use App\Models\UserStructureAssignment;
use App\Services\MailIdentityService;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailRegistryIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_numbers_are_isolated_by_structure(): void
    {
        $sikasso = Structure::query()->create([
            'code' => 'TR_SIKASSO',
            'name' => 'Tresorerie Sikasso',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $segou = Structure::query()->create([
            'code' => 'TR_SEGOU',
            'name' => 'Tresorerie Segou',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $service = Structure::query()->create([
            'code' => 'SRV_A',
            'name' => 'Service A',
            'parent_id' => $sikasso->id,
            'is_active' => true,
            'is_access_boundary' => false,
        ]);

        $numbering = app(NumberingService::class);
        $year = now()->year;

        $first = $numbering->generateArrivalNumber($sikasso->id);
        $secondStructure = $numbering->generateArrivalNumber($segou->id);
        $secondSame = $numbering->generateArrivalNumber($service->id);

        $this->assertSame("ARR/TRSIKASSO/{$year}/000001", $first);
        $this->assertSame("ARR/TRSEGOU/{$year}/000001", $secondStructure);
        $this->assertSame("ARR/TRSIKASSO/{$year}/000002", $secondSame);
    }

    public function test_letterhead_and_signatories_follow_the_structure_and_stay_frozen(): void
    {
        $sikasso = Structure::query()->create([
            'code' => 'TR_SIKASSO',
            'name' => 'Tresorerie Sikasso',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $dsi = Structure::query()->create([
            'code' => 'DSI',
            'name' => 'Direction des Systemes',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);

        $position = StructurePosition::query()->create([
            'structure_id' => $sikasso->id,
            'code' => 'PAYEUR',
            'name' => 'Payeur regional',
            'can_sign' => true,
            'is_active' => true,
        ]);
        $holder = User::factory()->create([
            'name' => 'Awa Dembele',
            'is_active' => true,
            'structure_id' => $sikasso->id,
            'must_change_password' => false,
        ]);
        UserStructureAssignment::query()->create([
            'user_id' => $holder->id,
            'structure_id' => $sikasso->id,
            'structure_position_id' => $position->id,
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ]);

        $identity = app(MailIdentityService::class);
        $sikassoIdentity = $identity->forStructure($sikasso->fresh());
        $dsiIdentity = $identity->forStructure($dsi->fresh());

        $this->assertStringContainsString('TRESORERIE SIKASSO', $sikassoIdentity['header']);
        $this->assertStringContainsString('DIRECTION DES SYSTEMES', $dsiIdentity['header']);
        $this->assertNotSame($sikassoIdentity['header'], $dsiIdentity['header']);
        $this->assertSame('Awa Dembele — Payeur regional', $sikassoIdentity['signatory_left']);
        $this->assertNotSame($sikassoIdentity['signatory_left'], $dsiIdentity['signatory_left']);
        $this->assertStringNotContainsString('Directeur Général', $sikassoIdentity['signatory_left']);

        $type = \App\Models\StructureType::query()->create([
            'code' => 'TYPE_TR',
            'name' => 'Tresorerie',
            'sort_order' => 30,
        ]);
        $sikasso->update(['structure_type_id' => $type->id]);
        \App\Models\StructureTypeDocumentProfile::query()->create([
            'structure_type_id' => $type->id,
            'header_title' => 'Tresorerie regionale',
            'append_structure_name' => true,
            'signatory_left_label' => 'Le Payeur',
            'signatory_right_label' => 'Le Fonde de pouvoir',
        ]);

        $configured = $identity->forStructure($sikasso->fresh());
        $this->assertSame('TRESORERIE REGIONALE', $configured['header']);
        $this->assertSame('TRESORERIE SIKASSO', $configured['structure_line']);
        $this->assertSame('Le Payeur', $configured['signatory_left']);
        $this->assertSame('Le Fonde de pouvoir', $configured['signatory_right']);

        $mail = Correspondence::query()->create([
            'direction' => 'sortant',
            'medium' => 'physique',
            'status' => 'projet_reponse',
            'subject' => 'Lettre Sikasso',
            'correspondence_date' => now()->toDateString(),
            'structure_id' => $sikasso->id,
            'registered_by' => $holder->id,
        ]);

        $identity->freeze($mail);
        $sikasso->update(['name' => 'Nom modifie']);

        $frozen = $identity->forCorrespondence($mail->fresh());
        $this->assertSame('TRESORERIE REGIONALE', $frozen['header']);
        $this->assertSame('TRESORERIE SIKASSO', $frozen['structure_line']);
        $this->assertStringNotContainsString('NOM MODIFIE', $frozen['header']);
        $this->assertStringNotContainsString('NOM MODIFIE', (string) $frozen['structure_line']);

        $mail->forceFill([
            'letterhead_snapshot' => ['header' => 'ANCIEN ENTETE'],
            'letterhead_frozen_at' => now(),
        ])->save();

        $legacy = $identity->forCorrespondence($mail->fresh());
        $this->assertSame('ANCIEN ENTETE', $legacy['header']);
        $this->assertArrayHasKey('structure_line', $legacy);
        $this->assertNull($legacy['structure_line']);

        $identity->freeze($mail->fresh(), force: true);
        $refreshed = $identity->forCorrespondence($mail->fresh());
        $this->assertSame('Le Payeur', $refreshed['signatory_left']);
        $this->assertSame('Le Fonde de pouvoir', $refreshed['signatory_right']);
    }
}
