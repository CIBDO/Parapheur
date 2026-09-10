<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinute extends Model
{
    protected $fillable = [
        'meeting_id',
        'kind',
        'version_number',
        'body',
        'payload',
        'status',
        'document_id',
        'generated_by',
        'validated_by',
        'generated_at',
        'submitted_at',
        'validated_at',
        'diffused_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'diffused_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
