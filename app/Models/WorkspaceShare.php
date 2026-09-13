<?php

namespace App\Models;

use App\Enums\WorkspaceShareAbility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceShare extends Model
{
    protected $fillable = [
        'workspace_id',
        'folder_id',
        'document_id',
        'grantee_user_id',
        'ability',
        'valid_from',
        'valid_until',
        'shared_by',
    ];

    protected function casts(): array
    {
        return [
            'ability' => WorkspaceShareAbility::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFolder::class, 'folder_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantee_user_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function isCurrentlyValid(): bool
    {
        $now = now();

        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }
}
