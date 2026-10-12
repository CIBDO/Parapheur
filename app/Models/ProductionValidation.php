<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionValidation extends Model
{
    protected $fillable = [
        'production_occurrence_id',
        'step_order',
        'validator_id',
        'decision',
        'motif',
        'comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ProductionOccurrence::class, 'production_occurrence_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }
}
