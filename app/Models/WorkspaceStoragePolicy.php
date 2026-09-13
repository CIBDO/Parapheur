<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceStoragePolicy extends Model
{
    protected $fillable = [
        'default_personal_quota_bytes',
        'default_shared_quota_bytes',
        'max_upload_bytes',
        'allowed_extensions',
        'denied_extensions',
        'trash_retention_days',
        'warn_threshold_percent',
        'block_on_exceed',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_personal_quota_bytes' => 'integer',
            'default_shared_quota_bytes' => 'integer',
            'max_upload_bytes' => 'integer',
            'allowed_extensions' => 'array',
            'denied_extensions' => 'array',
            'trash_retention_days' => 'integer',
            'warn_threshold_percent' => 'integer',
            'block_on_exceed' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
