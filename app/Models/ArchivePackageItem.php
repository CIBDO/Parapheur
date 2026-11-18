<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivePackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'record_id',
        'document_id',
        'document_version_id',
        'relative_path',
        'checksum',
        'size_bytes',
        'mime_type',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ArchivePackage::class, 'package_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
