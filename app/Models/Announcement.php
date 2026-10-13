<?php

namespace App\Models;

use App\Enums\AnnouncementConfidentiality;
use App\Enums\AnnouncementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'category_id',
        'importance_id',
        'structure_id',
        'author_id',
        'publisher_id',
        'validator_id',
        'cover_document_id',
        'title',
        'summary',
        'body',
        'confidentiality',
        'status',
        'current_version',
        'approval_step',
        'publish_at',
        'published_at',
        'expires_at',
        'acknowledgement_required',
        'banner_enabled',
        'banner_priority',
        'banner_starts_at',
        'banner_ends_at',
        'banner_scroll',
        'banner_duration_seconds',
        'dismissible',
    ];

    protected function casts(): array
    {
        return [
            'confidentiality' => AnnouncementConfidentiality::class,
            'status' => AnnouncementStatus::class,
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'banner_starts_at' => 'datetime',
            'banner_ends_at' => 'datetime',
            'acknowledgement_required' => 'boolean',
            'banner_enabled' => 'boolean',
            'banner_scroll' => 'boolean',
            'dismissible' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AnnouncementCategory::class, 'category_id');
    }

    public function importance(): BelongsTo
    {
        return $this->belongsTo(AnnouncementImportance::class, 'importance_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function coverDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'cover_document_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(AnnouncementAudience::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AnnouncementRevision::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AnnouncementApproval::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(AnnouncementLink::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(AnnouncementAcknowledgement::class);
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(AnnouncementFavorite::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnnouncementEvent::class);
    }
}
