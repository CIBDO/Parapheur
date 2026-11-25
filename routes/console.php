<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappels automatiques conservés : productions, réunions, RDV.
// Les autres remind-* / escalate-tasks restent disponibles en Artisan manuel
// mais ne sont plus planifiés (politique mails allégée).

Schedule::command('parapheur:remind-meetings')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/meeting-reminders.log'));

Schedule::command('parapheur:remind-appointments')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/appointment-reminders.log'));

Schedule::command('parapheur:check-ticket-sla')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ticket-sla.log'));

Schedule::command('parapheur:auto-close-tickets')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ticket-auto-close.log'));

Schedule::command('parapheur:purge-workspace-trash')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/workspace-trash-purge.log'));

Schedule::command('parapheur:generate-productions --sync')
    ->dailyAt('00:15')
    ->timezone('Africa/Bamako')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/production-generation.log'));

// Fenêtre matinale (pas un créneau d’une seule minute) : sur hébergement partagé
// le cron peut glisser de quelques minutes et rater dailyAt('07:30').
// Le service déduplique déjà (1 mail / producteur / jour).
Schedule::command('parapheur:remind-productions')
    ->everyFifteenMinutes()
    ->between('07:30', '10:00')
    ->timezone('Africa/Bamako')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/production-reminders.log'));

// Drain la file (mails ShouldQueue : transmissions productions, etc.).
// Suffit avec le seul cron schedule:run ; le 2ᵉ cron queue:work reste optionnel.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/queue-work.log'));

Schedule::command('parapheur:publish-announcements')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/announcement-publication.log'));

Schedule::command('parapheur:expire-announcements')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/announcement-expiration.log'));

Schedule::command('archives:verify-integrity --limit=200')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/archives-integrity.log'));
