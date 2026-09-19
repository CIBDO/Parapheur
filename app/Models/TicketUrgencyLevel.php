<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketUrgencyLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'level',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'urgency_id');
    }

    public function matrixEntries(): HasMany
    {
        return $this->hasMany(TicketPriorityMatrix::class, 'urgency_id');
    }
}
