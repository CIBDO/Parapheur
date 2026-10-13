<?php

namespace App\Models;

use App\Enums\AnnouncementCircuit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'validation_circuit',
        'default_importance_id',
        'default_confidentiality',
        'retention_days',
        'acknowledgement_required',
        'revalidate_on_change',
        'notify_on_publish',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'validation_circuit' => AnnouncementCircuit::class,
            'acknowledgement_required' => 'boolean',
            'revalidate_on_change' => 'boolean',
            'notify_on_publish' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function defaultImportance(): BelongsTo
    {
        return $this->belongsTo(AnnouncementImportance::class, 'default_importance_id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'category_id');
    }
}
