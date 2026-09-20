<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('parapheur:remind-overdue-instructions')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/instruction-reminders.log'));

Schedule::command('parapheur:remind-meetings')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/meeting-reminders.log'));

Schedule::command('parapheur:remind-appointments')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/appointment-reminders.log'));

Schedule::command('parapheur:remind-mail')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/mail-reminders.log'));

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
