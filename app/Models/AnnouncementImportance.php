<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementImportance extends Model
{
    protected $fillable = [
        'code',
        'name',
        'color',
        'rank',
        'banner_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'banner_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'importance_id');
    }
}
