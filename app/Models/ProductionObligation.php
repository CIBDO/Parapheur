<?php

namespace App\Models;

use App\Enums\DocumentConfidentiality;
use App\Enums\ProductionFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionObligation extends Model
{
    protected $fillable = [
        'code',
        'label',
        'description',
        'category',
        'domain',
        'structure_id',
        'producer_id',
        'deputy_id',
        'calendar_id',
        'frequency',
        'schedule_config',
        'deadline_time',
        'timezone',
        'valid_from',
        'valid_until',
        'confidentiality',
        'deliverable_type',
        'allowed_formats',
        'min_files',
        'max_files',
        'max_file_size_kb',
        'template_document_id',
        'validation_required',
        'validation_mode',
        'auto_transmit',
        'submit_to_parapheur',
        'workflow_id',
        'archive_enabled',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => ProductionFrequency::class,
            'schedule_config' => 'array',
            'allowed_formats' => 'array',
            'confidentiality' => DocumentConfidentiality::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
            'validation_required' => 'boolean',
            'auto_transmit' => 'boolean',
            'submit_to_parapheur' => 'boolean',
            'archive_enabled' => 'boolean',
            'is_active' => 'boolean',
            'min_files' => 'integer',
            'max_files' => 'integer',
            'max_file_size_kb' => 'integer',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_id');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(SlaCalendar::class, 'calendar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function templateDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'template_document_id');
    }

    public function actors(): HasMany
    {
        return $this->hasMany(ProductionObligationActor::class);
    }

    public function reminderRules(): HasMany
    {
        return $this->hasMany(ProductionReminderRule::class);
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(ProductionSuspension::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ProductionOccurrence::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProductionObligationHistory::class);
    }

    public function validators(): HasMany
    {
        return $this->actors()->where('role', 'validator')->orderBy('step_order');
    }

    public function recipients(): HasMany
    {
        return $this->actors()->where('role', 'recipient');
    }

    public function contributors(): HasMany
    {
        return $this->actors()->where('role', 'contributor');
    }

    public function isSuspendedOn(\Carbon\CarbonInterface $date): bool
    {
        return $this->suspensions()
            ->whereDate('starts_on', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date);
            })
            ->exists();
    }
}
