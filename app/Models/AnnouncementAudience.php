<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementAudience extends Model
{
    protected $fillable = [
        'announcement_id',
        'mode',
        'target_type',
        'structure_id',
        'user_id',
        'role_name',
        'include_descendants',
    ];

    protected function casts(): array
    {
        return [
            'include_descendants' => 'boolean',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
