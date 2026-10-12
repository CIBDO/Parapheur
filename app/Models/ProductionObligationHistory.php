<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionObligationHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'production_obligation_id',
        'user_id',
        'event',
        'before',
        'after',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
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
