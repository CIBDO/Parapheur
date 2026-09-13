<?php

namespace App\Models;

use App\Enums\CorrespondenceDirection;
use App\Enums\CorrespondenceMedium;
use App\Enums\CorrespondenceStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Correspondence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'document_id',
        'direction',
        'medium',
        'status',
        'arrival_number',
        'departure_number',
        'received_at',
        'correspondence_date',
        'registered_at',
        'due_date',
        'external_reference',
        'subject',
        'summary',
        'observations',
        'channel_id',
        'category_id',
        'qualification_id',
        'priority',
        'confidentiality',
        'structure_id',
        'registered_by',
        'owner_user_id',
        'piece_count',
        'keywords',
        'requires_reply',
        'is_registered',
        'reply_to_correspondence_id',
        'parapheur_document_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CorrespondenceDirection::class,
            'medium' => CorrespondenceMedium::class,
            'status' => CorrespondenceStatus::class,
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
            'received_at' => 'datetime',
            'correspondence_date' => 'date',
            'registered_at' => 'datetime',
            'due_date' => 'date',
            'keywords' => 'array',
            'requires_reply' => 'boolean',
            'is_registered' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Correspondence $correspondence) {
            if (! $correspondence->uuid) {
                $correspondence->uuid = (string) Str::uuid();
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceChannel::class, 'channel_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceCategory::class, 'category_id');
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceQualification::class, 'qualification_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'reply_to_correspondence_id');
    }

    public function parapheurDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parapheur_document_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(CorrespondenceParty::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CorrespondenceAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CorrespondenceEvent::class)->orderByDesc('created_at');
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(CorrespondenceLink::class, 'source_correspondence_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(CorrespondenceLink::class, 'target_correspondence_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CorrespondenceReminder::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(CorrespondenceDispatch::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(CorrespondenceAcknowledgement::class);
    }

    public function circulationSheets(): HasMany
    {
        return $this->hasMany(CirculationSheet::class);
    }
}
