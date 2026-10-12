<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReminderRule extends Model
{
    protected $fillable = [
        'production_obligation_id',
        'offset_type',
        'offset_minutes',
        'target',
        'escalate',
        'repeat_every_minutes',
        'stop_when',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
            'escalate' => 'boolean',
            'repeat_every_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ProductionObligation::class, 'production_obligation_id');
    }
}
