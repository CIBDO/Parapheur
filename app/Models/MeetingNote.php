<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingNote extends Model
{
    protected $fillable = [
        'meeting_id',
        'agenda_item_id',
        'author_id',
        'visibility',
        'section',
        'body',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'privee';
    }
}
