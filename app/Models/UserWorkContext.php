<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWorkContext extends Model
{
    protected $fillable = [
        'user_id',
        'organizational_role_assignment_id',
        'structure_id',
        'unit_structure_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OrganizationalRoleAssignment::class, 'organizational_role_assignment_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'unit_structure_id');
    }
}
