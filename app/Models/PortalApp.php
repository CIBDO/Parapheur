<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalApp extends Model
{
    protected $fillable = [
        'code',
        'name',
        'url',
        'icon',
        'color',
        'description',
        'category',
        'sort_order',
        'is_active',
        'open_in_new_tab',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
