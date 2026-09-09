<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'starts_on',
        'ends_on',
        'document_type_ids',
        'allowed_actions',
        'is_active',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'document_type_ids' => 'array',
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

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        return $today->betweenIncluded($this->starts_on->startOfDay(), $this->ends_on->endOfDay());
    }
}
