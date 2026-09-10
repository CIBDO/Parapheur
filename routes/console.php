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
