<?php

namespace App\Models;

use App\Enums\QuotaScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceQuotaOverride extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'quota_bytes',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => QuotaScopeType::class,
            'quota_bytes' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
