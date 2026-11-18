<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'import_type',
        'status',
        'structure_id',
        'created_by',
        'original_filename',
        'row_count',
        'valid_count',
        'duplicate_count',
        'error_count',
        'committed_count',
        'column_mapping',
        'preview_rows',
        'report',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'preview_rows' => 'array',
            'report' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
