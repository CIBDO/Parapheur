<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'production_occurrence_id',
        'production_obligation_id',
        'user_id',
        'event',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ProductionOccurrence::class, 'production_occurrence_id');
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ProductionObligation::class, 'production_obligation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
