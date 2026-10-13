<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementNotificationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'dedupe_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
