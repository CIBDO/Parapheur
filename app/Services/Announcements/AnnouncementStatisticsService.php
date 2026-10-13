<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementCircuit;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AnnouncementStatisticsService
{
    public function __construct(private readonly AnnouncementAccess $access) {}

    /** @return array<string, mixed> */
    public function summarize(User $user): array
    {
        if (! $user->can('announcement.view_statistics') && ! $user->can('announcement.manage')) {
            abort(403);
        }

        $query = $this->scoped($user);
        $base = (clone $query);

        $published = (clone $base)->where('status', AnnouncementStatus::Published);
        $active = (clone $published)->where(function (Builder $builder) {
            $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });

        $recipientRows = (clone $base)
            ->whereIn('status', [AnnouncementStatus::Published, AnnouncementStatus::Expired, AnnouncementStatus::Archived])
            ->withCount(['recipients as recipient_count' => function (Builder $builder) {
                $builder->whereColumn('announcement_recipients.version', 'announcements.current_version');
            }, 'reads as read_count' => function (Builder $builder) {
                $builder->whereColumn('announcement_reads.version', 'announcements.current_version');
            }, 'acknowledgements as ack_count' => function (Builder $builder) {
                $builder->whereColumn('announcement_acknowledgements.version', 'announcements.current_version');
            }])
            ->get();

        $recipients = (int) $recipientRows->sum('recipient_count');
        $reads = (int) $recipientRows->sum('read_count');
        $acks = (int) $recipientRows->sum('ack_count');
        $ackRequiredRecipients = (int) (clone $base)
            ->where('acknowledgement_required', true)
            ->whereIn('status', [AnnouncementStatus::Published, AnnouncementStatus::Expired, AnnouncementStatus::Archived])
            ->withCount(['recipients as recipient_count' => function (Builder $builder) {
                $builder->whereColumn('announcement_recipients.version', 'announcements.current_version');
            }])
            ->get()
            ->sum('recipient_count');

        $delays = (clone $published)->whereNotNull('published_at')->get(['created_at', 'published_at']);
        $avgHours = $delays->isEmpty()
            ? null
            : round($delays->avg(fn (Announcement $announcement) => $announcement->created_at?->diffInMinutes($announcement->published_at) / 60), 1);

        return [
            'published' => (clone $published)->count(),
            'active' => (clone $active)->count(),
            'expired' => (clone $base)->where('status', AnnouncementStatus::Expired)->count(),
            'pending_validation' => (clone $base)->where('status', AnnouncementStatus::PendingValidation)->count(),
            'urgent' => (clone $active)->whereHas('importance', fn (Builder $builder) => $builder->where('rank', '>=', 4))->count(),
            'recipients' => $recipients,
            'consultations' => $reads,
            'acknowledgements' => $acks,
            'acknowledgement_rate' => $ackRequiredRecipients > 0 ? round($acks / $ackRequiredRecipients, 3) : null,
            'average_publication_hours' => $avgHours,
            'by_category' => (clone $base)
                ->selectRaw('category_id, count(*) as total')
                ->groupBy('category_id')
                ->with('category:id,name')
                ->get()
                ->map(fn (Announcement $row) => [
                    'category' => $row->category?->name,
                    'total' => (int) $row->total,
                ])
                ->values(),
            'by_structure' => (clone $base)
                ->selectRaw('structure_id, count(*) as total')
                ->groupBy('structure_id')
                ->with('structure:id,name,code')
                ->get()
                ->map(fn (Announcement $row) => [
                    'structure' => $row->structure?->name,
                    'total' => (int) $row->total,
                ])
                ->values(),
        ];
    }

    /** @return Builder<Announcement> */
    private function scoped(User $user): Builder
    {
        $query = Announcement::query();
        if (! $this->access->seesAllStatistics($user)) {
            $query->where('structure_id', $user->structure_id);
        }
        if ($this->access->hidesSensitiveAggregates($user)) {
            $query->whereNotIn('confidentiality', ['restricted', 'confidential']);
        }

        return $query;
    }
}
