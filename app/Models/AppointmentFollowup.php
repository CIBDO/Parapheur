<?php

namespace App\Models;

use App\Enums\AppointmentFollowupKind;
use App\Enums\DocumentPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentFollowup extends Model
{
    protected $fillable = [
        'appointment_id',
        'kind',
        'title',
        'description',
        'priority',
        'due_date',
        'assignee_id',
        'structure_id',
        'instruction_id',
        'followup_appointment_id',
        'followup_meeting_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AppointmentFollowupKind::class,
            'priority' => DocumentPriority::class,
            'due_date' => 'date',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }

    public function followupAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'followup_appointment_id');
    }

    public function followupMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'followup_meeting_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
