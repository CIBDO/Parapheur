<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveRetentionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_id',
        'rule_id',
        'event_type',
        'due_at',
        'actor_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'payload' => 'array',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ArchiveRetentionRule::class, 'rule_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
