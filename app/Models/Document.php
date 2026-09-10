<?php

namespace App\Models;

use App\Enums\DocumentConfidentiality;
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
        'object',
        'document_type_id',
        'structure_id',
        'author_id',
        'current_assignee_id',
        'status',
        'priority',
        'confidentiality',
        'expected_action',
        'document_date',
        'due_date',
        'keywords',
        'current_version',
        'submitted_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
            'expected_action' => ExpectedAction::class,
            'keywords' => 'array',
            'document_date' => 'date',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'archived_at' => 'datetime',
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

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
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

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
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
}
