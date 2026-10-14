<?php

namespace App\Models;

use App\Services\Organization\StructureBoundaryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Structure extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'structure_type_id',
        'code',
        'name',
        'is_active',
        'is_access_boundary',
        'boundary_structure_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_access_boundary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Structure $structure) {
            if ($structure->wasRecentlyCreated || $structure->wasChanged(['parent_id', 'is_access_boundary'])) {
                app(StructureBoundaryService::class)->refreshAll();
            }
        });

        static::updated(function (Structure $structure) {
            $watched = ['name', 'code', 'parent_id', 'structure_type_id', 'is_active', 'is_access_boundary'];
            if (! $structure->wasChanged($watched)) {
                return;
            }

            StructureChangeLog::query()->create([
                'structure_id' => $structure->id,
                'user_id' => auth()->id(),
                'event' => 'updated',
                'before' => collect($structure->getOriginal())->only($watched)->all(),
                'after' => collect($structure->getAttributes())->only($watched)->all(),
                'created_at' => now(),
            ]);
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StructureType::class, 'structure_type_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function boundary(): BelongsTo
    {
        return $this->belongsTo(self::class, 'boundary_structure_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(StructurePosition::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(OrganizationalRoleAssignment::class);
    }
}
