<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementCircuit;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\AnnouncementApproval;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementAudience;
use App\Models\AnnouncementCategory;
use App\Models\AnnouncementEvent;
use App\Models\AnnouncementFavorite;
use App\Models\AnnouncementImportance;
use App\Models\AnnouncementLink;
use App\Models\AnnouncementRead;
use App\Models\AnnouncementRecipient;
use App\Models\AnnouncementRevision;
use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentAccessService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AnnouncementService
{
    public function __construct(
        private readonly AnnouncementHtmlSanitizer $sanitizer,
        private readonly AnnouncementAudienceResolver $audiences,
        private readonly AnnouncementAccess $access,
        private readonly AnnouncementNotificationService $notifications,
        private readonly DocumentAccessService $documents,
        private readonly AuditLogger $audit,
    ) {}

    public function feed(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->baseQuery($user)
            ->whereIn('status', [AnnouncementStatus::Published, AnnouncementStatus::Expired, AnnouncementStatus::Archived])
            ->whereHas('recipients', function (Builder $builder) use ($user) {
                $builder->where('user_id', $user->id)
                    ->whereColumn('announcement_recipients.version', 'announcements.current_version');
            });

        if (! empty($filters['archive'])) {
            $query->whereIn('status', [AnnouncementStatus::Expired, AnnouncementStatus::Archived]);
        } elseif (empty($filters['status'])) {
            $query->where('status', AnnouncementStatus::Published);
        }

        $this->applyFilters($query, $user, $filters);

        return $query->orderByDesc('published_at')->orderByDesc('id')->paginate(15);
    }

    public function managed(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->baseQuery($user)->where(function (Builder $builder) use ($user) {
            $builder->where('author_id', $user->id)
                ->orWhere('validator_id', $user->id)
                ->orWhere('publisher_id', $user->id);

            if ($user->can('announcement.approve') || $user->can('announcement.publish') || $user->can('announcement.manage')) {
                $builder->orWhere(function (Builder $inner) use ($user) {
                    $inner->where('structure_id', $user->structure_id)
                        ->whereNotIn('confidentiality', ['restricted', 'confidential']);
                });
            }

            if ($this->access->isInstitutionalActor($user) && ($user->can('announcement.approve') || $user->can('announcement.publish'))) {
                $builder->orWhere(function (Builder $inner) {
                    $inner->where('status', AnnouncementStatus::PendingValidation)
                        ->whereHas('category', fn (Builder $category) => $category->where('validation_circuit', AnnouncementCircuit::Institutional))
                        ->whereNotIn('confidentiality', ['restricted', 'confidential']);
                });
            }
        });

        $this->applyFilters($query, $user, $filters);

        return $query->orderByDesc('updated_at')->paginate(15);
    }

    /** @return Collection<int, Announcement> */
    public function banner(User $user): Collection
    {
        return Announcement::query()
            ->with([
                'category:id,name,code',
                'importance:id,code,name,color,rank',
                'acknowledgements' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->where('status', AnnouncementStatus::Published)
            ->where('banner_enabled', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('banner_starts_at')->orWhere('banner_starts_at', '<=', now());
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('banner_ends_at')->orWhere('banner_ends_at', '>', now());
            })
            ->whereHas('recipients', function (Builder $builder) use ($user) {
                $builder->where('user_id', $user->id)
                    ->whereColumn('announcement_recipients.version', 'announcements.current_version');
            })
            ->where(function (Builder $builder) use ($user) {
                $builder->whereDoesntHave('dismissals', function (Builder $dismissal) use ($user) {
                    $dismissal->where('user_id', $user->id)
                        ->whereColumn('announcement_dismissals.version', 'announcements.current_version');
                })->orWhere(function (Builder $required) use ($user) {
                    $required->where('acknowledgement_required', true)
                        ->whereDoesntHave('acknowledgements', function (Builder $ack) use ($user) {
                            $ack->where('user_id', $user->id)
                                ->whereColumn('announcement_acknowledgements.version', 'announcements.current_version');
                        });
                });
            })
            ->join('announcement_importances', 'announcement_importances.id', '=', 'announcements.importance_id')
            ->select('announcements.*')
            ->orderByDesc('announcement_importances.rank')
            ->orderByDesc('announcements.banner_priority')
            ->orderByDesc('announcements.published_at')
            ->limit(12)
            ->get();
    }

    public function create(User $actor, array $data): Announcement
    {
        return DB::transaction(function () use ($actor, $data) {
            $category = AnnouncementCategory::query()->findOrFail($data['category_id']);
            $importance = AnnouncementImportance::query()->find($data['importance_id'] ?? $category->default_importance_id);
            $data['importance_id'] = $data['importance_id'] ?? $category->default_importance_id;
            $data['confidentiality'] = $data['confidentiality'] ?? $category->default_confidentiality;
            $data['acknowledgement_required'] = $data['acknowledgement_required'] ?? $category->acknowledgement_required;
            $data['banner_enabled'] = $data['banner_enabled'] ?? (bool) $importance?->banner_default;

            $announcement = new Announcement;
            $announcement->reference = $this->nextReference();
            $announcement->author_id = $actor->id;
            $announcement->status = AnnouncementStatus::Draft;
            $announcement->current_version = 1;
            $announcement->approval_step = 0;
            $this->fill($announcement, $data, $actor);
            $announcement->save();
            $this->syncRelations($actor, $announcement, $data);
            $this->event($announcement, $actor, 'created');

            return $announcement->refresh()->load($this->detailRelations());
        });
    }

    public function update(User $actor, Announcement $announcement, array $data): Announcement
    {
        if (! $this->access->canUpdate($actor, $announcement)) {
            abort(403);
        }

        if (in_array($announcement->status, [AnnouncementStatus::Cancelled, AnnouncementStatus::Archived], true)) {
            throw ValidationException::withMessages(['status' => 'Cette annonce ne peut plus être modifiée.']);
        }

        return DB::transaction(function () use ($actor, $announcement, $data) {
            $announcement = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $announcement->load('category', 'audiences');
            $substantial = $this->isSubstantial($announcement, $data);
            $revalidate = (bool) $announcement->category?->revalidate_on_change;

            if ($announcement->status === AnnouncementStatus::Published && $substantial) {
                $this->storeRevision($announcement, $actor, 'Modification après publication');
                $announcement->current_version = (int) $announcement->current_version + 1;
                if ($revalidate && $announcement->category?->validation_circuit !== AnnouncementCircuit::Direct) {
                    $announcement->status = AnnouncementStatus::PendingValidation;
                    $announcement->approval_step = 1;
                }
            } elseif (in_array($announcement->status, [AnnouncementStatus::Published, AnnouncementStatus::Expired], true) && ! $substantial) {
                if ($announcement->status === AnnouncementStatus::Expired && $this->prolongs($data)) {
                    if (! $this->access->canPublish($actor, $announcement)) {
                        abort(403);
                    }
                    $announcement->status = AnnouncementStatus::Published;
                }
            }

            $this->fill($announcement, $data, $actor);
            $announcement->save();
            $this->syncRelations($actor, $announcement, $data);

            if ($announcement->status === AnnouncementStatus::Published && $substantial && (! $revalidate || $announcement->category?->validation_circuit === AnnouncementCircuit::Direct)) {
                $this->snapshot($announcement);
                $this->notifications->published($announcement);
            }

            if ($announcement->status === AnnouncementStatus::PendingValidation && $substantial) {
                $this->notifications->validationRequested($announcement);
            }

            $this->event($announcement, $actor, $substantial ? 'revised' : 'updated');

            return $announcement->refresh()->load($this->detailRelations());
        });
    }

    public function submit(User $actor, Announcement $announcement): Announcement
    {
        if (! $this->access->canSubmit($actor, $announcement)) {
            abort(403);
        }

        $announcement->load('category', 'audiences');
        if (! in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::ToCorrect, AnnouncementStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => 'Cette annonce ne peut pas être soumise.']);
        }

        if ($announcement->category?->validation_circuit === AnnouncementCircuit::Direct) {
            if (! $actor->can('announcement.publish')) {
                throw ValidationException::withMessages(['status' => 'La publication directe requiert le droit de publier.']);
            }

            return $this->publish($actor, $announcement);
        }

        $announcement->status = AnnouncementStatus::PendingValidation;
        $announcement->approval_step = 1;
        $announcement->save();
        $this->approval($announcement, $actor, 1, 'submit', null);
        $this->event($announcement, $actor, 'submitted');
        $this->notifications->validationRequested($announcement);

        return $announcement->refresh();
    }

    public function approve(User $actor, Announcement $announcement, ?string $comment): Announcement
    {
        $announcement->load('category');
        if (! $this->access->canApprove($actor, $announcement)) {
            abort(403);
        }

        $step = (int) $announcement->approval_step;
        $this->approval($announcement, $actor, $step, 'approve', $comment);

        if ($announcement->category?->validation_circuit === AnnouncementCircuit::Institutional && $step < 2) {
            $announcement->approval_step = 2;
            $announcement->save();
            $this->event($announcement, $actor, 'approved_step', ['comment' => $comment]);
            $this->notifications->validationRequested($announcement);

            return $announcement->refresh();
        }

        $announcement->status = AnnouncementStatus::Approved;
        $announcement->save();
        $this->event($announcement, $actor, 'approved', ['comment' => $comment]);
        $this->notifications->decision($announcement, 'approved', 'Annonce approuvée', 'Votre annonce « '.$announcement->title.' » est approuvée.');

        return $announcement->refresh();
    }

    public function reject(User $actor, Announcement $announcement, ?string $comment): Announcement
    {
        $announcement->load('category');
        if (! $this->access->canApprove($actor, $announcement)) {
            abort(403);
        }

        $announcement->status = AnnouncementStatus::Rejected;
        $announcement->save();
        $this->approval($announcement, $actor, (int) $announcement->approval_step, 'reject', $comment);
        $this->event($announcement, $actor, 'rejected', ['comment' => $comment]);
        $this->notifications->decision($announcement, 'rejected', 'Annonce rejetée', $comment ?: 'Votre annonce a été rejetée.');

        return $announcement->refresh();
    }

    public function requestCorrection(User $actor, Announcement $announcement, ?string $comment): Announcement
    {
        $announcement->load('category');
        if (! $this->access->canApprove($actor, $announcement)) {
            abort(403);
        }

        $announcement->status = AnnouncementStatus::ToCorrect;
        $announcement->save();
        $this->approval($announcement, $actor, (int) $announcement->approval_step, 'request_correction', $comment);
        $this->event($announcement, $actor, 'correction_requested', ['comment' => $comment]);
        $this->notifications->decision($announcement, 'correction_requested', 'Correction demandée', $comment ?: 'Une correction est demandée avant publication.');

        return $announcement->refresh();
    }

    public function publish(User $actor, Announcement $announcement, bool $fromScheduler = false): Announcement
    {
        return DB::transaction(function () use ($actor, $announcement, $fromScheduler) {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            if ($locked->status === AnnouncementStatus::Published) {
                return $locked;
            }

            $locked->load('category', 'audiences', 'importance');
            if ($fromScheduler && $locked->status !== AnnouncementStatus::Scheduled) {
                return $locked;
            }

            if (! $fromScheduler && ! $this->access->canPublish($actor, $locked)) {
                abort(403);
            }

            $circuit = $locked->category?->validation_circuit;
            if (! $fromScheduler && $circuit !== AnnouncementCircuit::Direct && ! in_array($locked->status, [AnnouncementStatus::Approved, AnnouncementStatus::Scheduled], true)) {
                throw ValidationException::withMessages(['status' => 'L\'annonce doit être approuvée avant publication.']);
            }

            if (! $fromScheduler && $locked->publish_at && $locked->publish_at->isFuture()) {
                $locked->status = AnnouncementStatus::Scheduled;
                $locked->publisher_id = $actor->id;
                $locked->save();
                $this->event($locked, $actor, 'scheduled');

                return $locked->refresh();
            }

            $locked->status = AnnouncementStatus::Published;
            $locked->published_at = now();
            $locked->publisher_id = $locked->publisher_id ?: $actor->id;
            $locked->save();
            $this->snapshot($locked);
            $this->event($locked, $fromScheduler ? null : $actor, 'published');
            $this->notifications->published($locked);

            return $locked->refresh();
        });
    }

    public function schedule(User $actor, Announcement $announcement, Carbon $when): Announcement
    {
        if (! $actor->can('announcement.schedule') && ! $actor->can('announcement.publish')) {
            abort(403);
        }

        if ($when->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['publish_at' => 'La date de publication doit être ultérieure.']);
        }

        $announcement->load('category');
        $announcement->publish_at = $when;
        $circuit = $announcement->category?->validation_circuit;
        if ($circuit === AnnouncementCircuit::Direct || $announcement->status === AnnouncementStatus::Approved) {
            if (! $this->access->canPublish($actor, $announcement)) {
                abort(403);
            }
            $announcement->status = AnnouncementStatus::Scheduled;
            $announcement->publisher_id = $actor->id;
        }
        $announcement->save();
        $this->event($announcement, $actor, 'scheduled');

        return $announcement->refresh();
    }

    public function unpublish(User $actor, Announcement $announcement): Announcement
    {
        if (! $actor->can('announcement.unpublish') && ! $actor->can('announcement.publish')) {
            abort(403);
        }

        $announcement->status = AnnouncementStatus::Cancelled;
        $announcement->save();
        $this->event($announcement, $actor, 'unpublished');

        return $announcement->refresh();
    }

    public function archive(User $actor, Announcement $announcement): Announcement
    {
        if (! $actor->can('announcement.archive') && ! $actor->can('announcement.manage')) {
            abort(403);
        }

        $announcement->status = AnnouncementStatus::Archived;
        $announcement->save();
        $this->event($announcement, $actor, 'archived');

        return $announcement->refresh();
    }

    public function publishDue(): int
    {
        $count = 0;
        Announcement::query()
            ->where('status', AnnouncementStatus::Scheduled)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->orderBy('id')
            ->each(function (Announcement $announcement) use (&$count) {
                $actor = $announcement->publisher ?? $announcement->author;
                if (! $actor) {
                    return;
                }
                $this->publish($actor, $announcement, true);
                $count++;
            });

        return $count;
    }

    public function expireDue(): int
    {
        $count = 0;
        Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (Announcement $announcement) use (&$count) {
                $announcement->status = AnnouncementStatus::Expired;
                $announcement->save();
                $this->event($announcement, null, 'expired');
                $count++;
            });

        return $count;
    }

    public function markRead(User $user, Announcement $announcement): void
    {
        if (! $this->access->isCurrentRecipient($user, $announcement)) {
            return;
        }

        $read = AnnouncementRead::query()->firstOrNew([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'version' => $announcement->current_version,
        ]);
        $read->first_viewed_at = $read->first_viewed_at ?? now();
        $read->last_viewed_at = now();
        $read->save();
    }

    public function acknowledge(User $user, Announcement $announcement): AnnouncementAcknowledgement
    {
        if (! $this->access->isCurrentRecipient($user, $announcement) || $announcement->status !== AnnouncementStatus::Published) {
            abort(404);
        }

        $this->markRead($user, $announcement);

        return AnnouncementAcknowledgement::query()->updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'version' => $announcement->current_version,
            ],
            ['acknowledged_at' => now()]
        );
    }

    public function dismiss(User $user, Announcement $announcement): void
    {
        if (! $this->access->isCurrentRecipient($user, $announcement)) {
            abort(404);
        }

        $acknowledged = $announcement->acknowledgements()
            ->where('user_id', $user->id)
            ->where('version', $announcement->current_version)
            ->exists();

        if ($announcement->acknowledgement_required && ! $acknowledged) {
            throw ValidationException::withMessages([
                'dismiss' => 'Confirmez la lecture avant de masquer cette annonce.',
            ]);
        }

        if (! $announcement->dismissible && ! $acknowledged) {
            throw ValidationException::withMessages([
                'dismiss' => 'Cette annonce ne peut pas être masquée.',
            ]);
        }

        $announcement->dismissals()->updateOrCreate(
            [
                'user_id' => $user->id,
                'version' => $announcement->current_version,
            ],
            ['dismissed_at' => now()]
        );
    }

    public function toggleFavorite(User $user, Announcement $announcement): bool
    {
        if (! $this->access->canView($user, $announcement)) {
            abort(404);
        }

        $existing = AnnouncementFavorite::query()
            ->where('announcement_id', $announcement->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        AnnouncementFavorite::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        return true;
    }

    /** @return array<string, mixed> */
    public function receipts(User $user, Announcement $announcement): array
    {
        if (! $this->access->canViewReceipts($user, $announcement)) {
            abort(403);
        }

        $rows = AnnouncementRecipient::query()
            ->with('user:id,name,structure_id')
            ->where('announcement_id', $announcement->id)
            ->where('version', $announcement->current_version)
            ->orderBy('user_id')
            ->get();

        $reads = AnnouncementRead::query()
            ->where('announcement_id', $announcement->id)
            ->where('version', $announcement->current_version)
            ->get()
            ->keyBy('user_id');
        $acks = AnnouncementAcknowledgement::query()
            ->where('announcement_id', $announcement->id)
            ->where('version', $announcement->current_version)
            ->get()
            ->keyBy('user_id');

        $items = $rows->map(function (AnnouncementRecipient $row) use ($reads, $acks) {
            $read = $reads->get($row->user_id);
            $ack = $acks->get($row->user_id);

            return [
                'user_id' => $row->user_id,
                'name' => $row->user?->name,
                'consulted_at' => $read?->first_viewed_at,
                'acknowledged_at' => $ack?->acknowledged_at,
                'version' => $row->version,
            ];
        })->values();

        $total = $items->count();
        $confirmed = $items->whereNotNull('acknowledged_at')->count();

        return [
            'total' => $total,
            'consultations' => $items->whereNotNull('consulted_at')->count(),
            'confirmations' => $confirmed,
            'rate' => $total > 0 ? round($confirmed / $total, 3) : null,
            'items' => $items,
        ];
    }

    /** @param  list<array<string, mixed>>  $rules
     * @return array{count: int, sample: list<array{id: int, name: string}>}
     */
    public function previewRules(array $rules): array
    {
        $normalized = $this->normalizeAudiences($rules);

        return [
            'count' => count($this->audiences->resolveIds($normalized)),
            'sample' => $this->audiences->sample($normalized),
        ];
    }

    /** @return array<string, mixed> */
    public function present(Announcement $announcement, User $user, bool $detail = false): array
    {
        $version = (int) $announcement->current_version;
        $read = $announcement->relationLoaded('reads')
            ? $announcement->reads->first(fn ($row) => (int) $row->user_id === (int) $user->id && (int) $row->version === $version)
            : AnnouncementRead::query()->where('announcement_id', $announcement->id)->where('user_id', $user->id)->where('version', $version)->first();
        $ack = $announcement->relationLoaded('acknowledgements')
            ? $announcement->acknowledgements->first(fn ($row) => (int) $row->user_id === (int) $user->id && (int) $row->version === $version)
            : AnnouncementAcknowledgement::query()->where('announcement_id', $announcement->id)->where('user_id', $user->id)->where('version', $version)->first();
        $favorite = $announcement->relationLoaded('favorites')
            ? $announcement->favorites->contains(fn ($row) => (int) $row->user_id === (int) $user->id)
            : AnnouncementFavorite::query()->where('announcement_id', $announcement->id)->where('user_id', $user->id)->exists();

        $payload = [
            'id' => $announcement->id,
            'reference' => $announcement->reference,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'content' => $this->plainText($announcement->body) ?: (string) ($announcement->summary ?? ''),
            'status' => $announcement->status->value,
            'confidentiality' => $announcement->confidentiality->value,
            'current_version' => $version,
            'publish_at' => $announcement->publish_at,
            'published_at' => $announcement->published_at,
            'expires_at' => $announcement->expires_at,
            'acknowledgement_required' => $announcement->acknowledgement_required,
            'banner_enabled' => $announcement->banner_enabled,
            'banner_priority' => $announcement->banner_priority,
            'banner_starts_at' => $announcement->banner_starts_at,
            'banner_ends_at' => $announcement->banner_ends_at,
            'banner_scroll' => $announcement->banner_scroll,
            'banner_duration_seconds' => $announcement->banner_duration_seconds,
            'dismissible' => $announcement->dismissible,
            'viewed' => $read !== null,
            'acknowledged' => $ack !== null,
            'favorite' => $favorite,
            'category' => $announcement->category ? [
                'id' => $announcement->category->id,
                'code' => $announcement->category->code,
                'name' => $announcement->category->name,
                'validation_circuit' => $announcement->category->validation_circuit?->value,
            ] : null,
            'importance' => $announcement->importance ? [
                'id' => $announcement->importance->id,
                'code' => $announcement->importance->code,
                'name' => $announcement->importance->name,
                'color' => $announcement->importance->color,
                'rank' => $announcement->importance->rank,
            ] : null,
            'structure' => $announcement->structure ? [
                'id' => $announcement->structure->id,
                'code' => $announcement->structure->code,
                'name' => $announcement->structure->name,
            ] : null,
            'author' => $announcement->author ? [
                'id' => $announcement->author->id,
                'name' => $announcement->author->name,
            ] : null,
        ];

        if (! $detail) {
            return $payload;
        }

        $payload['body'] = $announcement->body;
        $payload['approval_step'] = $announcement->approval_step;
        $payload['validator_id'] = $announcement->validator_id;
        $payload['links'] = $announcement->links->map(fn (AnnouncementLink $link) => [
            'id' => $link->id,
            'label' => $link->label,
            'url' => $link->url,
        ])->values();
        $payload['attachments'] = $announcement->attachments->map(fn (AnnouncementAttachment $attachment) => [
            'id' => $attachment->id,
            'document_id' => $attachment->document_id,
            'label' => $attachment->label ?: $attachment->document?->object,
        ])->values();

        if ($this->access->canManageRecord($user, $announcement) || (int) $announcement->author_id === (int) $user->id) {
            $payload['audiences'] = $announcement->audiences->map(fn (AnnouncementAudience $audience) => [
                'mode' => $audience->mode,
                'target_type' => $audience->target_type,
                'structure_id' => $audience->structure_id,
                'user_id' => $audience->user_id,
                'role_name' => $audience->role_name,
                'include_descendants' => $audience->include_descendants,
            ])->values();
            $payload['approvals'] = $announcement->approvals->map(fn (AnnouncementApproval $approval) => [
                'step' => $approval->step,
                'action' => $approval->action,
                'comment' => $approval->comment,
                'actor' => $approval->actor?->name,
                'created_at' => $approval->created_at,
            ])->values();
            $payload['revisions'] = $announcement->revisions->map(fn (AnnouncementRevision $revision) => [
                'version' => $revision->version,
                'title' => $revision->title,
                'change_note' => $revision->change_note,
                'created_at' => $revision->created_at,
            ])->values();
            $payload['events'] = $announcement->events->map(fn (AnnouncementEvent $event) => [
                'event' => $event->event,
                'actor' => $event->actor?->name,
                'properties' => $event->properties,
                'created_at' => $event->created_at,
            ])->values();
        }

        return $payload;
    }

    /** @param  Builder<Announcement>  $query */
    private function applyFilters(Builder $query, User $user, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('title', 'like', $term)
                    ->orWhere('summary', 'like', $term)
                    ->orWhere('reference', 'like', $term);
            });
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }
        if (! empty($filters['importance_id'])) {
            $query->where('importance_id', $filters['importance_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('published_at', '>=', $this->bamako((string) $filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->where('published_at', '<=', $this->bamako((string) $filters['to']));
        }
        if (! empty($filters['highlight'])) {
            $query->where('status', AnnouncementStatus::Published)
                ->where(function (Builder $builder) {
                    $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereHas('importance', fn (Builder $builder) => $builder->where('rank', '>=', 3));
        }
        if (! empty($filters['unread'])) {
            $query->whereDoesntHave('reads', function (Builder $builder) use ($user) {
                $builder->where('user_id', $user->id)
                    ->whereColumn('announcement_reads.version', 'announcements.current_version');
            });
        }
        if (! empty($filters['acknowledgement'])) {
            $query->where('acknowledgement_required', true)
                ->whereDoesntHave('acknowledgements', function (Builder $builder) use ($user) {
                    $builder->where('user_id', $user->id)
                        ->whereColumn('announcement_acknowledgements.version', 'announcements.current_version');
                });
        }
    }

    /** @return Builder<Announcement> */
    private function baseQuery(User $user): Builder
    {
        return Announcement::query()->with([
            'category:id,code,name,validation_circuit',
            'importance:id,code,name,color,rank',
            'structure:id,code,name',
            'author:id,name',
            'reads' => fn ($query) => $query->where('user_id', $user->id),
            'acknowledgements' => fn ($query) => $query->where('user_id', $user->id),
            'favorites' => fn ($query) => $query->where('user_id', $user->id),
        ]);
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'category',
            'importance',
            'structure',
            'author',
            'audiences',
            'links',
            'attachments.document',
            'approvals.actor',
            'revisions',
            'events.actor',
            'reads',
            'acknowledgements',
            'favorites',
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Announcement $announcement, array $data, User $actor): void
    {
        $announcement->fill([
            'category_id' => $data['category_id'] ?? $announcement->category_id,
            'importance_id' => $data['importance_id'] ?? $announcement->importance_id,
            'structure_id' => $data['structure_id'] ?? $announcement->structure_id ?? $actor->structure_id,
            'validator_id' => array_key_exists('validator_id', $data) ? $data['validator_id'] : $announcement->validator_id,
            'title' => $data['title'] ?? $announcement->title,
            'summary' => array_key_exists('summary', $data) ? $data['summary'] : $announcement->summary,
            'confidentiality' => $data['confidentiality'] ?? $announcement->confidentiality ?? 'general',
            'acknowledgement_required' => array_key_exists('acknowledgement_required', $data)
                ? (bool) $data['acknowledgement_required']
                : (bool) $announcement->acknowledgement_required,
            'banner_enabled' => array_key_exists('banner_enabled', $data) ? (bool) $data['banner_enabled'] : (bool) $announcement->banner_enabled,
            'banner_priority' => $data['banner_priority'] ?? $announcement->banner_priority ?? 0,
            'banner_scroll' => array_key_exists('banner_scroll', $data) ? (bool) $data['banner_scroll'] : ($announcement->banner_scroll ?? true),
            'banner_duration_seconds' => $data['banner_duration_seconds'] ?? $announcement->banner_duration_seconds ?? 8,
            'dismissible' => array_key_exists('dismissible', $data) ? (bool) $data['dismissible'] : ($announcement->dismissible ?? true),
        ]);

        if (array_key_exists('body', $data)) {
            $announcement->body = $this->sanitizer->clean($data['body']);
        }
        foreach (['publish_at', 'expires_at', 'banner_starts_at', 'banner_ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $announcement->{$field} = $this->bamako($data[$field] ? (string) $data[$field] : null);
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    private function syncRelations(User $actor, Announcement $announcement, array $data): void
    {
        if (array_key_exists('audiences', $data)) {
            $this->syncAudiences($announcement, $data['audiences']);
        }
        if (array_key_exists('links', $data)) {
            $this->syncLinks($announcement, $data['links']);
        }
        if (array_key_exists('document_ids', $data)) {
            $this->syncDocuments($actor, $announcement, $data['document_ids']);
        }
    }

    /** @param  list<array<string, mixed>>  $rules */
    private function syncAudiences(Announcement $announcement, array $rules): void
    {
        $normalized = $this->normalizeAudiences($rules);
        $hasInclude = collect($normalized)->contains(fn (array $rule) => $rule['mode'] === 'include');
        if (! $hasInclude) {
            throw ValidationException::withMessages(['audiences' => 'Indiquez au moins une règle d\'inclusion.']);
        }

        $announcement->audiences()->delete();
        foreach ($normalized as $rule) {
            $announcement->audiences()->create($rule);
        }
    }

    /** @param  list<array<string, mixed>>  $links */
    private function syncLinks(Announcement $announcement, array $links): void
    {
        $announcement->links()->delete();
        foreach (array_values($links) as $index => $link) {
            $url = (string) ($link['url'] ?? '');
            if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
                throw ValidationException::withMessages(['links' => 'Les liens doivent être des chemins internes.']);
            }
            $announcement->links()->create([
                'label' => $link['label'] ?? 'Lien',
                'url' => $url,
                'sort_order' => $index,
            ]);
        }
    }

    /** @param  list<int>  $documentIds */
    private function syncDocuments(User $actor, Announcement $announcement, array $documentIds): void
    {
        $keep = [];
        foreach (array_values($documentIds) as $index => $documentId) {
            $document = Document::query()->find($documentId);
            if (! $document || ! $this->documents->canAccess($actor, $document)) {
                throw ValidationException::withMessages(['document_ids' => 'Un document joint n\'est pas accessible.']);
            }
            $keep[] = $document->id;
            AnnouncementAttachment::query()->updateOrCreate(
                ['announcement_id' => $announcement->id, 'document_id' => $document->id],
                ['label' => $document->object, 'sort_order' => $index]
            );
        }

        $announcement->attachments()->when($keep !== [], fn ($query) => $query->whereNotIn('document_id', $keep))->delete();
        if ($keep === []) {
            $announcement->attachments()->delete();
        }
    }

    private function snapshot(Announcement $announcement): void
    {
        $announcement->load('audiences');
        $rules = $announcement->audiences->map(fn (AnnouncementAudience $audience) => [
            'mode' => $audience->mode,
            'target_type' => $audience->target_type,
            'structure_id' => $audience->structure_id,
            'user_id' => $audience->user_id,
            'role_name' => $audience->role_name,
            'include_descendants' => $audience->include_descendants,
        ])->all();
        $ids = $this->audiences->resolveIds($rules);
        if ($ids === []) {
            throw ValidationException::withMessages(['audiences' => 'Aucun destinataire actif pour cette audience.']);
        }

        AnnouncementRecipient::query()
            ->where('announcement_id', $announcement->id)
            ->where('version', $announcement->current_version)
            ->delete();

        $now = now();
        foreach (array_chunk($ids, 400) as $chunk) {
            AnnouncementRecipient::query()->insert(array_map(fn (int $id) => [
                'announcement_id' => $announcement->id,
                'user_id' => $id,
                'version' => $announcement->current_version,
                'snapshotted_at' => $now,
            ], $chunk));
        }
    }

    /** @param  array<string, mixed>  $data */
    private function isSubstantial(Announcement $announcement, array $data): bool
    {
        $map = [
            'title' => (string) $announcement->title,
            'summary' => (string) $announcement->summary,
            'category_id' => (string) $announcement->category_id,
            'importance_id' => (string) $announcement->importance_id,
            'confidentiality' => (string) $announcement->confidentiality->value,
        ];
        foreach ($map as $key => $current) {
            if (array_key_exists($key, $data) && (string) $data[$key] !== $current) {
                return true;
            }
        }
        if (array_key_exists('body', $data) && $this->sanitizer->clean($data['body']) !== (string) $announcement->body) {
            return true;
        }
        if (array_key_exists('acknowledgement_required', $data) && (bool) $data['acknowledgement_required'] !== (bool) $announcement->acknowledgement_required) {
            return true;
        }
        if (array_key_exists('audiences', $data)) {
            $current = $announcement->audiences->map(fn (AnnouncementAudience $audience) => [
                'mode' => $audience->mode,
                'target_type' => $audience->target_type,
                'structure_id' => $audience->structure_id,
                'user_id' => $audience->user_id,
                'role_name' => $audience->role_name,
                'include_descendants' => $audience->include_descendants,
            ])->all();

            return $this->audienceSignature($current) !== $this->audienceSignature($this->normalizeAudiences($data['audiences']));
        }

        return false;
    }

    /** @param  array<string, mixed>  $data */
    private function prolongs(array $data): bool
    {
        if (! array_key_exists('expires_at', $data) || ! $data['expires_at']) {
            return false;
        }

        return $this->bamako((string) $data['expires_at'])?->isFuture() ?? false;
    }

    private function storeRevision(Announcement $announcement, User $actor, string $note): void
    {
        AnnouncementRevision::query()->create([
            'announcement_id' => $announcement->id,
            'version' => $announcement->current_version,
            'editor_id' => $actor->id,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'body' => $announcement->body,
            'change_note' => $note,
            'created_at' => now(),
        ]);
    }

    private function approval(Announcement $announcement, User $actor, int $step, string $action, ?string $comment): void
    {
        AnnouncementApproval::query()->create([
            'announcement_id' => $announcement->id,
            'step' => max(1, $step),
            'actor_id' => $actor->id,
            'action' => $action,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $properties */
    private function event(Announcement $announcement, ?User $actor, string $name, array $properties = []): void
    {
        AnnouncementEvent::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $actor?->id,
            'event' => $name,
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
        $this->audit->log('announcement.'.$name, $announcement, $properties);
    }

    private function nextReference(): string
    {
        $year = now('Africa/Bamako')->year;
        $prefix = sprintf('ANN-%d-', $year);
        $last = Announcement::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->lockForUpdate()
            ->value('reference');
        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function normalizeAudiences(array $rules): array
    {
        return array_map(fn (array $rule) => [
            'mode' => ($rule['mode'] ?? 'include') === 'exclude' ? 'exclude' : 'include',
            'target_type' => (string) ($rule['target_type'] ?? ''),
            'structure_id' => ! empty($rule['structure_id']) ? (int) $rule['structure_id'] : null,
            'user_id' => ! empty($rule['user_id']) ? (int) $rule['user_id'] : null,
            'role_name' => isset($rule['role_name']) && $rule['role_name'] !== '' ? (string) $rule['role_name'] : null,
            'include_descendants' => (bool) ($rule['include_descendants'] ?? false),
        ], $rules);
    }

    /** @param  list<array<string, mixed>>  $rules */
    private function audienceSignature(array $rules): string
    {
        $normalized = $this->normalizeAudiences($rules);
        usort($normalized, fn (array $left, array $right) => strcmp(json_encode($left), json_encode($right)));

        return json_encode($normalized) ?: '';
    }

    private function plainText(?string $html): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function bamako(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, 'Africa/Bamako')->utc();
    }
}
