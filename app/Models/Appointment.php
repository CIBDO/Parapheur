<?php

namespace App\Models;

use App\Enums\AppointmentMeetingMode;
use App\Enums\AppointmentOriginType;
use App\Enums\AppointmentStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'appointment_type_id',
        'subject',
        'reason',
        'description',
        'requested_date',
        'requested_start_time',
        'requested_end_time',
        'proposed_availabilities',
        'start_at',
        'end_at',
        'previous_start_at',
        'previous_end_at',
        'location',
        'meeting_mode',
        'visio_url',
        'external_address',
        'external_host_organization',
        'external_contact',
        'logistics_info',
        'origin_type',
        'origin_reference',
        'intake_channel',
        'requester_type',
        'requester_user_id',
        'requester_name',
        'requester_organization',
        'requester_position',
        'requester_email',
        'requester_phone',
        'director_id',
        'secretariat_id',
        'structure_id',
        'redirected_to_user_id',
        'redirected_to_structure_id',
        'priority',
        'confidentiality',
        'status',
        'blocks_calendar',
        'context_note',
        'points_to_discuss',
        'decision_note',
        'internal_note',
        'result_summary',
        'rejection_reason',
        'rejection_communicable',
        'cancellation_reason',
        'reschedule_reason',
        'complement_request',
        'parent_appointment_id',
        'converted_meeting_id',
        'linked_document_id',
        'validated_by',
        'validated_at',
        'confirmed_by',
        'confirmed_at',
        'started_at',
        'finished_at',
        'closed_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'proposed_availabilities' => 'array',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'previous_start_at' => 'datetime',
            'previous_end_at' => 'datetime',
            'validated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'blocks_calendar' => 'boolean',
            'rejection_communicable' => 'boolean',
            'status' => AppointmentStatus::class,
            'meeting_mode' => AppointmentMeetingMode::class,
            'origin_type' => AppointmentOriginType::class,
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function secretariat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretariat_id');
    }

    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function parentAppointment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_appointment_id');
    }

    public function followupsAppointments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_appointment_id');
    }

    public function convertedMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'converted_meeting_id');
    }

    public function linkedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'linked_document_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AppointmentParticipant::class);
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(AppointmentDocument::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'appointment_documents')
            ->withPivot(['id', 'kind', 'label', 'sort_order', 'attached_by'])
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(AppointmentNote::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(AppointmentFollowup::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function durationMinutes(): ?int
    {
        if (! $this->start_at || ! $this->end_at) {
            return $this->type?->default_duration_minutes;
        }

        return (int) $this->start_at->diffInMinutes($this->end_at);
    }

    public function displaySubject(): string
    {
        return $this->subject ?: ('Rendez-vous #'.$this->id);
    }

    public function requesterDisplayName(): string
    {
        return $this->requester_name
            ?: $this->requesterUser?->name
            ?: '—';
    }
}
