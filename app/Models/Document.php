<?php

namespace App\Models;

use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\ExpectedAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'dossier_number',
        'object',
        'title',
        'description',
        'summary',
        'document_type_id',
        'category_id',
        'structure_id',
        'owner_structure_id',
        'classification_node_id',
        'author_id',
        'current_assignee_id',
        'status',
        'priority',
        'confidentiality',
        'expected_action',
        'document_date',
        'due_date',
        'keywords',
        'origin',
        'language',
        'source',
        'current_version',
        'submitted_at',
        'archived_at',
        'archive_status',
        'retention_years',
        'text_extraction_status',
        'ocr_status',
        'ocr_processed_at',
        'indexed_at',
        'antivirus_status',
        'legal_hold_at',
        'legal_hold_reason',
        'retention_rule_id',
        'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
            'expected_action' => ExpectedAction::class,
            'origin' => DocumentOrigin::class,
            'archive_status' => DocumentArchiveStatus::class,
            'keywords' => 'array',
            'document_date' => 'date',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'archived_at' => 'datetime',
            'ocr_processed_at' => 'datetime',
            'indexed_at' => 'datetime',
            'legal_hold_at' => 'datetime',
            'retention_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            if (! $document->uuid) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function ownerStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'owner_structure_id');
    }

    public function classificationNode(): BelongsTo
    {
        return $this->belongsTo(ClassificationNode::class, 'classification_node_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assignee_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->where('is_main', true)->latestOfMany('version_number');
    }

    public function officialVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->where('is_official', true)->latestOfMany('version_number');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'document_tag_document')
            ->withTimestamps();
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(DocumentAccessRule::class);
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'source_document_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'target_document_id');
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(DocumentTransmission::class);
    }

    public function workflowInstance(): HasOne
    {
        return $this->hasOne(WorkflowInstance::class)->latestOfMany();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderByDesc('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderByDesc('created_at');
    }

    public function visas(): HasMany
    {
        return $this->hasMany(Visa::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function instructions(): HasMany
    {
        return $this->hasMany(Instruction::class);
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_documents')
            ->withPivot(['sort_order', 'agenda_label', 'kind', 'agenda_item_id'])
            ->withTimestamps();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(DocumentFavorite::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(DocumentView::class);
    }

    public function indexContents(): HasMany
    {
        return $this->hasMany(DocumentIndexContent::class);
    }

    public function retentionRule(): BelongsTo
    {
        return $this->belongsTo(DocumentRetentionRule::class, 'retention_rule_id');
    }

    public function isOnLegalHold(): bool
    {
        return $this->legal_hold_at !== null
            || $this->archive_status === DocumentArchiveStatus::Gele;
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->object;
    }
}
