<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberingSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'year',
        'prefix',
        'padding',
        'last_value',
        'reset_yearly',
        'structure_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_value' => 'integer',
            'reset_yearly' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
