<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDelegation extends Model
{
    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'task_id',
        'starts_on',
        'ends_on',
        'allowed_actions',
        'is_active',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'allowed_actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        return $today->betweenIncluded($this->starts_on->startOfDay(), $this->ends_on->endOfDay());
    }

    public function allows(string $action): bool
    {
        $actions = $this->allowed_actions;
        if (! is_array($actions) || $actions === []) {
            return true;
        }

        return in_array($action, $actions, true) || in_array('*', $actions, true);
    }
}
