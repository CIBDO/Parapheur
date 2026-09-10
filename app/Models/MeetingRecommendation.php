<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRecommendation extends Model
{
    protected $fillable = [
        'meeting_id',
        'agenda_item_id',
        'structure_id',
        'created_by',
        'title',
        'body',
        'status',
        'converted_decision_id',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedDecision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'converted_decision_id');
    }
}
