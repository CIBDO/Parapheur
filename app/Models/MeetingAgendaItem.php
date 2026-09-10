<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingAgendaItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'item_number',
        'title',
        'description',
        'presenter_id',
        'duration_minutes',
        'sort_order',
        'confidentiality',
        'status',
        'preparatory_notes',
        'is_follow_up',
    ];

    protected function casts(): array
    {
        return [
            'is_follow_up' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingNote::class, 'agenda_item_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class, 'agenda_item_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MeetingDocument::class, 'agenda_item_id');
    }
}
