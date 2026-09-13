<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'user_id',
        'reminder_date',
        'type',
        'note',
        'is_sent',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
