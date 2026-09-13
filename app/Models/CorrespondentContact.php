<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondentContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondent_id',
        'type',
        'value',
        'label',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function correspondent(): BelongsTo
    {
        return $this->belongsTo(Correspondent::class);
    }
}
