<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionObligationActor extends Model
{
    protected $fillable = [
        'production_obligation_id',
        'role',
        'user_id',
        'structure_id',
        'role_name',
        'step_order',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
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

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
