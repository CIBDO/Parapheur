<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketNotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'database_enabled',
        'mail_enabled',
        'muted_events',
    ];

    protected function casts(): array
    {
        return [
            'database_enabled' => 'boolean',
            'mail_enabled' => 'boolean',
            'muted_events' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
