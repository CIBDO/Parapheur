<?php

namespace App\Models;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'title',
        'object',
        'description',
        'meeting_type_id',
        'meeting_date',
        'meeting_time',
        'end_time',
        'location',
        'visio_url',
        'chair_id',
        'structure_id',
        'secretary_id',
        'organizer_id',
        'created_by',
        'agenda',
        'notes',
        'status',
        'confidentiality',
        'priority',
        'is_recurring',
        'parent_meeting_id',
        'recurrence_id',
        'observations',
        'cancellation_reason',
        'previous_meeting_date',
        'previous_meeting_time',
        'previous_location',
        'started_at',
        'ended_at',
        'locked_at',
        'lock_version',
        'current_agenda_item_id',
        'convocation_document_id',
        'minutes_document_id',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'previous_meeting_date' => 'date',
            'is_recurring' => 'boolean',
            'status' => MeetingStatus::class,
            'confidentiality' => DocumentConfidentiality::class,
            'priority' => DocumentPriority::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MeetingType::class, 'meeting_type_id');
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function parentMeeting(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_meeting_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_meeting_id');
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(MeetingRecurrence::class, 'recurrence_id');
    }

    public function currentAgendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'current_agenda_item_id');
    }

    public function convocationDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'convocation_document_id');
    }

    public function minutesDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'minutes_document_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['role', 'invitation_status', 'confirmation_status', 'attendance_status'])
            ->withTimestamps();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'meeting_documents')
            ->withPivot(['sort_order', 'agenda_label', 'kind', 'agenda_item_id'])
            ->withTimestamps();
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(MeetingDocument::class)->orderBy('sort_order');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class);
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('sort_order')->orderBy('item_number');
    }

    public function sessionNotes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(MeetingRecommendation::class);
    }

    public function minutes(): HasMany
    {
        return $this->hasMany(MeetingMinute::class)->orderByDesc('version_number');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->orderByDesc('created_at');
    }

    public function displayTitle(): string
    {
        return $this->object ?: $this->title;
    }
}
