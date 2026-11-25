<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\ProductionMorningDigestNotification;
use App\Support\NotificationPresentation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class InstitutionalMailCopyTest extends TestCase
{
    public function test_action_expected_helper_prefixes_text(): void
    {
        $this->assertSame(
            'Action attendue : transmettre le livrable.',
            NotificationPresentation::actionExpected('transmettre le livrable.')
        );
        $this->assertSame('', NotificationPresentation::actionExpected('  '));
    }

    public function test_production_morning_digest_uses_institutional_copy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 08:00:00', 'Africa/Bamako'));

        $user = new User(['name' => 'Lamine COULIBALY', 'email' => 'lamine@example.test']);
        $items = Collection::make([
            (object) [
                'id' => 1,
                'reference' => 'PRO/2026/000017',
                'label' => 'Situation des dépenses',
                'deadline_at' => Carbon::parse('2026-11-25 10:00:00', 'Africa/Bamako'),
            ],
        ]);

        $mail = (new ProductionMorningDigestNotification($items))->toMail($user);
        $subject = $mail->subject;
        $rendered = implode("\n", $mail->introLines);

        $this->assertStringContainsString('Productions du jour à transmettre — 25/11/2026', (string) $subject);
        $this->assertStringContainsString('obligations de production quotidienne', $rendered);
        $this->assertStringContainsString('Situation des dépenses', $rendered);
        $this->assertStringContainsString('PRO/2026/000017', $rendered);
        $this->assertStringContainsString('Action attendue :', $rendered);
        $this->assertStringNotContainsString("Bonjour, certains éléments", $rendered);

        $bonjourCount = substr_count(mb_strtolower($rendered), 'bonjour');
        $this->assertSame(0, $bonjourCount, 'Le corps ne doit pas contenir un second Bonjour (déjà dans greeting).');

        Carbon::setTestNow();
    }
}
