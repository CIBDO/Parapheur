<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTransmissionRecipient extends Model
{
    protected $fillable = [
        'production_transmission_id',
        'user_id',
        'structure_id',
        'resolution_source',
        'delivery_status',
        'notified_at',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    public function transmission(): BelongsTo
    {
        return $this->belongsTo(ProductionTransmission::class, 'production_transmission_id');
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
