<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEscalation extends Model
{
    protected $fillable = [
        'task_id',
        'level',
        'reason',
        'notified_user_id',
        'triggered_by',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'escalated_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function notifiedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_user_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
