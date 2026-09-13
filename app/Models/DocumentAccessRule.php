<?php

namespace App\Models;

use App\Enums\DocumentAccessAbility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'structure_id',
        'role_name',
        'ability',
        'granted_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'ability' => DocumentAccessAbility::class,
            'expires_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
