<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'title',
        'description',
        'status',
        'confidentiality',
        'source',
        'location_label',
        'channel_id',
        'requester_id',
        'structure_id',
        'ticket_type_id',
        'ticket_category_id',
        'service_item_id',
        'impact_id',
        'urgency_id',
        'priority_id',
        'support_team_id',
        'assignee_id',
        'parent_id',
        'application_id',
        'asset_id',
        'instruction_id',
        'document_id',
        'custom_fields',
        'resolution_summary',
        'cancellation_reason',
        'reopen_count',
        'is_major_incident',
        'taken_at',
        'resolved_at',
        'closed_at',
        'due_response_at',
        'due_resolution_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'custom_fields' => 'array',
            'reopen_count' => 'integer',
            'is_major_incident' => 'boolean',
            'taken_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'due_response_at' => 'datetime',
            'due_resolution_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(TicketImpactLevel::class, 'impact_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(TicketUrgencyLevel::class, 'urgency_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'support_team_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TicketChannel::class, 'channel_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TicketTag::class, 'ticket_tag_ticket');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function priorityHistories(): HasMany
    {
        return $this->hasMany(TicketPriorityHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(TicketWorklog::class);
    }

    public function satisfactions(): HasMany
    {
        return $this->hasMany(TicketSatisfaction::class);
    }

    public function sla(): HasOne
    {
        return $this->hasOne(TicketSla::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function ticketRelations(): HasMany
    {
        return $this->hasMany(TicketRelation::class);
    }

    public function problems(): BelongsToMany
    {
        return $this->belongsToMany(Problem::class, 'problem_ticket_links');
    }

    public function knowledgeArticles(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeArticle::class, 'knowledge_article_ticket_links');
    }

    public function actors(): HasMany
    {
        return $this->hasMany(TicketActor::class);
    }

    public function observers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_actors')
            ->wherePivot('role', 'observer')
            ->withTimestamps();
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'ticket_asset')->withTimestamps();
    }

    public function solutions(): HasMany
    {
        return $this->hasMany(TicketSolution::class)->orderByDesc('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class)->orderByDesc('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TicketApproval::class)->orderByDesc('id');
    }

    public function ola(): HasOne
    {
        return $this->hasOne(TicketOla::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TicketCost::class)->orderByDesc('id');
    }
}
