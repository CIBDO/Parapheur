<?php

namespace App\Models;

use App\Enums\AccessScopeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class OrganizationalRoleAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'structure_id',
        'unit_structure_id',
        'scope_type',
        'starts_on',
        'ends_on',
        'granted_by',
        'justification',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => AccessScopeType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'unit_structure_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('status', 'active')
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
            });
    }
}
