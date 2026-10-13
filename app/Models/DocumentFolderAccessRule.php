<?php

namespace App\Models;

use App\Enums\DocumentAccessAbility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFolderAccessRule extends Model
{
    protected $fillable = [
        'document_folder_id',
        'user_id',
        'structure_id',
        'role_name',
        'ability',
        'is_exception',
        'granted_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'ability' => DocumentAccessAbility::class,
            'is_exception' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'document_folder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
