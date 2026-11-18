<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveBoxContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_id',
        'record_id',
        'folder_code',
        'title',
        'date_start',
        'date_end',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'box_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }
}
