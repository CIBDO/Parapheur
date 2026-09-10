<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentNotification;
use Illuminate\Support\Facades\DB;

class AppointmentReminderService
{
    /**
     * Relances configurables : J-1, H-2, H-30.
     */
    public function dispatchDueReminders(): int
    {
        $count = 0;
        $windows = [
            'j1' => [now()->addDay()->subMinutes(30), now()->addDay()->addMinutes(30)],
            'h2' => [now()->addHours(2)->subMinutes(15), now()->addHours(2)->addMinutes(15)],
            'h30' => [now()->addMinutes(30)->subMinutes(5), now()->addMinutes(30)->addMinutes(5)],
        ];

        $appointments = Appointment::query()
            ->whereIn('status', [
                AppointmentStatus::Confirme->value,
                AppointmentStatus::Valide->value,
                AppointmentStatus::Pret->value,
            ])
            ->whereNotNull('start_at')
            ->where('start_at', '>', now())
            ->where('start_at', '<=', now()->addDay()->addHour())
            ->with(['director', 'secretariat', 'requesterUser', 'participants.user'])
            ->get();

        foreach ($appointments as $appointment) {
            foreach ($windows as $key => [$from, $to]) {
                if (! $appointment->start_at->between($from, $to)) {
                    continue;
                }

                $exists = DB::table('appointment_reminder_logs')
                    ->where('appointment_id', $appointment->id)
                    ->where('reminder_key', $key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $message = match ($key) {
                    'j1' => 'Rappel J-1 : rendez-vous demain à '.$appointment->start_at->format('H:i'),
                    'h2' => 'Rappel H-2 : rendez-vous dans environ 2 heures',
                    default => 'Rappel H-30 : rendez-vous dans environ 30 minutes',
                };

                $recipients = collect([
                    $appointment->director,
                    $appointment->secretariat,
                    $appointment->requesterUser,
                ])->filter();

                foreach ($appointment->participants as $participant) {
                    if ($participant->user) {
                        $recipients->push($participant->user);
                    }
                }

                foreach ($recipients->unique('id') as $user) {
                    $user->notify(new AppointmentNotification($appointment, 'reminder', $message));
                }

                DB::table('appointment_reminder_logs')->insert([
                    'appointment_id' => $appointment->id,
                    'reminder_key' => $key,
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }
}
