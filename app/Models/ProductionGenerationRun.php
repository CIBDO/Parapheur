<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionGenerationRun extends Model
{
    protected $fillable = [
        'target_date',
        'status',
        'created_count',
        'skipped_count',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }
}
