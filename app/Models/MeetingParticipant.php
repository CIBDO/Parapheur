<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'role',
        'participation_type',
        'is_required',
        'invitation_status',
        'confirmation_status',
        'attendance_status',
        'external_name',
        'external_function',
        'external_structure',
        'email',
        'phone',
        'representative_id',
        'representative_name',
        'notified_at',
        'read_at',
        'confirmed_at',
        'arrived_at',
        'left_at',
        'last_reminded_at',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'notified_at' => 'datetime',
            'read_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'left_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function displayName(): string
    {
        return $this->user?->name
            ?: $this->external_name
            ?: $this->email
            ?: 'Invité';
    }
}
