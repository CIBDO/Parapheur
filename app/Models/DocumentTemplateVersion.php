<?php

namespace App\Models;

use App\Enums\DocumentTemplateVersionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'version_number',
        'status',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'checksum',
        'created_by',
        'published_at',
        'change_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentTemplateVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
