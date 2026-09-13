<?php

namespace App\Models;

use App\Enums\WorkspaceType;
use App\Enums\WorkspaceVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'type',
        'owner_id',
        'structure_id',
        'visibility',
        'quota_bytes',
        'storage_used_bytes',
        'storage_recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
            'visibility' => WorkspaceVisibility::class,
            'quota_bytes' => 'integer',
            'storage_used_bytes' => 'integer',
            'storage_recalculated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workspace): void {
            if (! $workspace->uuid) {
                $workspace->uuid = (string) Str::uuid();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(WorkspaceFolder::class);
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(WorkspaceDocumentLink::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(WorkspaceShare::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkspaceActivity::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === WorkspaceType::Personal;
    }
}
