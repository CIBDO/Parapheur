<?php

namespace App\Models;

use App\Enums\ArchiveRecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'permanent_id',
        'reference',
        'title',
        'description',
        'status',
        'confidentiality',
        'structure_id',
        'producer_unit_id',
        'fonds_id',
        'series_id',
        'classification_node_id',
        'transfer_id',
        'box_id',
        'location_id',
        'retention_rule_id',
        'disposition_due_at',
        'final_disposition',
        'preservation_status',
        'last_integrity_at',
        'last_integrity_result',
        'document_id',
        'document_version_id',
        'checksum',
        'typology',
        'medium',
        'physical_reference',
        'date_start',
        'date_end',
        'created_by',
        'conserved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArchiveRecordStatus::class,
            'date_start' => 'date',
            'date_end' => 'date',
            'disposition_due_at' => 'date',
            'conserved_at' => 'datetime',
            'last_integrity_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function producerUnit(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'producer_unit_id');
    }

    public function fonds(): BelongsTo
    {
        return $this->belongsTo(ArchiveFonds::class, 'fonds_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ArchiveSeries::class, 'series_id');
    }

    public function classificationNode(): BelongsTo
    {
        return $this->belongsTo(ArchiveClassificationNode::class, 'classification_node_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ArchiveTransfer::class, 'transfer_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'box_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ArchiveStorageLocation::class, 'location_id');
    }

    public function retentionRule(): BelongsTo
    {
        return $this->belongsTo(ArchiveRetentionRule::class, 'retention_rule_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function retentionEvents(): HasMany
    {
        return $this->hasMany(ArchiveRetentionEvent::class, 'record_id');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(ArchiveAccessGrant::class, 'record_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ArchivePackage::class, 'record_id');
    }

    public function integrityChecks(): HasMany
    {
        return $this->hasMany(ArchiveIntegrityCheck::class, 'record_id');
    }

    public function preservationEvents(): HasMany
    {
        return $this->hasMany(ArchivePreservationEvent::class, 'record_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(ArchiveHold::class, 'record_id');
    }
}
