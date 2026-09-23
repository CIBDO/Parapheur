<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskValidation extends Model
{
    protected $fillable = [
        'task_id',
        'validator_id',
        'decision',
        'motif',
        'comment',
        'new_due_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'new_due_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }
}
