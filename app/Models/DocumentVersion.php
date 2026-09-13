<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'version_number',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'is_main',
        'is_official',
        'uploaded_by',
        'change_note',
        'change_source',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_official' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
