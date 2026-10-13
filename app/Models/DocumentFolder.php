<?php

namespace App\Models;

use App\Enums\DocumentFolderKind;
use App\Enums\PublicationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'structure_id',
        'code',
        'name',
        'description',
        'kind',
        'responsible_user_id',
        'path',
        'depth',
        'inherit_permissions',
        'is_archived',
        'default_publication_workflow',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DocumentFolderKind::class,
            'default_publication_workflow' => PublicationWorkflow::class,
            'inherit_permissions' => 'boolean',
            'is_archived' => 'boolean',
            'depth' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(DocumentFolderAccessRule::class, 'document_folder_id');
    }

    public function rebuildPath(): void
    {
        if ($this->parent_id) {
            $parent = $this->parent()->first();
            $parentPath = $parent?->path ?: '/';
            $this->path = rtrim($parentPath, '/').'/'.$this->id.'/';
            $this->depth = (int) ($parent?->depth ?? 0) + 1;
        } else {
            $this->path = '/'.$this->id.'/';
            $this->depth = 0;
        }
        $this->saveQuietly();
    }

    /**
     * @return list<int>
     */
    public function ancestorIdsIncludingSelf(): array
    {
        $ids = array_values(array_filter(array_map('intval', explode('/', trim((string) $this->path, '/')))));

        return $ids !== [] ? $ids : [(int) $this->id];
    }
}
