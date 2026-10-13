<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\AnnouncementNotificationLog;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use App\Notifications\AnnouncementReminderNotification;
use App\Notifications\AnnouncementWorkflowNotification;
use Illuminate\Support\Facades\Log;

final class AnnouncementNotificationService
{
    public function published(Announcement $announcement): int
    {
        if ($announcement->category && ! $announcement->category->notify_on_publish) {
            return 0;
        }

        $announcement->loadMissing('importance', 'category');
        $sent = 0;

        User::query()
            ->whereIn('id', $announcement->recipients()->where('version', $announcement->current_version)->pluck('user_id'))
            ->orderBy('id')
            ->each(function (User $user) use ($announcement, &$sent) {
                $key = sprintf('pub:%d:v%d:u%d', $announcement->id, $announcement->current_version, $user->id);
                if ($this->alreadySent($key)) {
                    return;
                }

                try {
                    $user->notify(new AnnouncementPublishedNotification($announcement));
                    $this->markSent($announcement, $user, $key);
                    $sent++;
                } catch (\Throwable $exception) {
                    Log::error('Notification d\'annonce non envoyée', [
                        'announcement_id' => $announcement->id,
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        return $sent;
    }

    public function validationRequested(Announcement $announcement): void
    {
        $announcement->loadMissing('category', 'author');
        $step = (int) $announcement->approval_step;
        $recipients = $this->approvers($announcement, $step);
        $headline = 'Annonce à valider';
        $message = ($announcement->author?->name ?? 'Un rédacteur').' a soumis « '.$announcement->title.' ».';

        foreach ($recipients as $user) {
            $key = sprintf('wf:%d:step%d:u%d', $announcement->id, $step, $user->id);
            if ($this->alreadySent($key)) {
                continue;
            }

            try {
                $user->notify(new AnnouncementWorkflowNotification($announcement, 'validation_requested', $headline, $message));
                $this->markSent($announcement, $user, $key);
            } catch (\Throwable $exception) {
                Log::error('Notification de validation non envoyée', [
                    'announcement_id' => $announcement->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function decision(Announcement $announcement, string $event, string $headline, string $message): void
    {
        $author = $announcement->author;
        if (! $author) {
            return;
        }

        $key = sprintf('wf:%d:%s:u%d:v%d', $announcement->id, $event, $author->id, $announcement->current_version);
        if ($this->alreadySent($key)) {
            return;
        }

        $author->notify(new AnnouncementWorkflowNotification($announcement, $event, $headline, $message));
        $this->markSent($announcement, $author, $key);
    }

    public function remindPending(): int
    {
        return $this->remindLoop(0, now('Africa/Bamako')->format('Ymd'));
    }

    public function remindAnnouncement(Announcement $announcement): int
    {
        return $this->remindOne($announcement, now('Africa/Bamako')->format('Ymd'));
    }

    private function remindLoop(int $sent, string $day): int
    {
        Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->where('acknowledgement_required', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('id')
            ->each(function (Announcement $announcement) use (&$sent, $day) {
                $sent += $this->remindOne($announcement, $day);
            });

        return $sent;
    }

    private function remindOne(Announcement $announcement, string $day): int
    {
        $sent = 0;
        $recipientIds = $announcement->recipients()
            ->where('version', $announcement->current_version)
            ->pluck('user_id');

        $users = User::query()
            ->whereIn('id', $recipientIds)
            ->whereDoesntHave('announcementAcknowledgements', function ($query) use ($announcement) {
                $query->where('announcement_id', $announcement->id)
                    ->where('version', $announcement->current_version);
            })
            ->get();

        foreach ($users as $user) {
            $key = sprintf('ack:%d:v%d:u%d:%s', $announcement->id, $announcement->current_version, $user->id, $day);
            if ($this->alreadySent($key)) {
                continue;
            }

            try {
                $user->notify(new AnnouncementReminderNotification($announcement));
                $this->markSent($announcement, $user, $key);
                $sent++;
            } catch (\Throwable $exception) {
                Log::error('Relance d\'accusé non envoyée', [
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /** @return list<User> */
    private function approvers(Announcement $announcement, int $step): array
    {
        $query = User::query()->where('is_active', true)->whereKeyNot($announcement->author_id);
        $circuit = $announcement->category?->validation_circuit?->value;

        if ($announcement->validator_id) {
            $validator = User::query()->whereKey($announcement->validator_id)->where('is_active', true)->first();

            return $validator ? [$validator] : [];
        }

        if ($circuit === 'institutional') {
            $permission = $step >= 2 ? 'announcement.publish' : 'announcement.approve';

            return $query->role(['Directeur Général', 'DGA', 'Secrétariat DG'])
                ->get()
                ->filter(fn (User $user) => $user->can($permission))
                ->values()
                ->all();
        }

        return $query
            ->where('structure_id', $announcement->structure_id)
            ->role(['Directeur', 'Chef de division', 'Directeur Général', 'DGA'])
            ->get()
            ->filter(fn (User $user) => $user->can('announcement.approve'))
            ->values()
            ->all();
    }

    private function alreadySent(string $key): bool
    {
        return AnnouncementNotificationLog::query()->where('dedupe_key', $key)->exists();
    }

    private function markSent(Announcement $announcement, User $user, string $key): void
    {
        AnnouncementNotificationLog::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'dedupe_key' => $key,
            'created_at' => now(),
        ]);
    }
}
