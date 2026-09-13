<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceAcknowledgement extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'acknowledged_at',
        'acknowledged_by_name',
        'method',
        'observations',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
