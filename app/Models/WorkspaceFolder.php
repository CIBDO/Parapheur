<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'name',
        'description',
        'owner_id',
        'position',
        'path',
        'depth',
    ];

    protected $appends = [
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'depth' => 'integer',
        ];
    }

    /**
     * Dossiers racine créés automatiquement (Mes projets, Modèles…).
     * On peut y ajouter du contenu, mais pas les renommer ni les supprimer.
     */
    public function getIsSystemAttribute(): bool
    {
        if ($this->parent_id !== null) {
            return false;
        }

        return in_array($this->name, \App\Services\WorkspaceBootstrapService::DEFAULT_PERSONAL_FOLDERS, true);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(WorkspaceDocumentLink::class, 'folder_id');
    }

    public function rebuildPath(): void
    {
        if ($this->parent_id) {
            $parent = $this->parent()->first();
            $parentPath = $parent?->path ?: '/';
            $this->path = rtrim($parentPath, '/').'/'.$this->id.'/';
            $this->depth = ($parent?->depth ?? 0) + 1;
        } else {
            $this->path = '/'.$this->id.'/';
            $this->depth = 0;
        }

        $this->saveQuietly();
    }
}
