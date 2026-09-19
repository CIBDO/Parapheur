<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketStatusRef extends Model
{
    use HasFactory;

    protected $table = 'ticket_statuses';

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'pauses_sla',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pauses_sla' => 'boolean',
        ];
    }
}
