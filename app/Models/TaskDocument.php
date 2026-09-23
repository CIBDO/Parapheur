<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDocument extends Model
{
    protected $fillable = [
        'task_id',
        'document_id',
        'linked_by',
        'role',
        'submitted_to_ged',
        'submitted_to_ged_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_to_ged' => 'boolean',
            'submitted_to_ged_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
