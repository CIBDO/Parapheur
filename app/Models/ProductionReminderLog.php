<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReminderLog extends Model
{
    protected $fillable = [
        'production_occurrence_id',
        'production_reminder_rule_id',
        'kind',
        'dedupe_key',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ProductionOccurrence::class, 'production_occurrence_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ProductionReminderRule::class, 'production_reminder_rule_id');
    }
}
