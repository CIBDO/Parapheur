<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'code',
        'is_system',
        'home_route',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
