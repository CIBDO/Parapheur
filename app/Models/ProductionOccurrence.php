<?php

namespace App\Models;

use App\Enums\ProductionOccurrenceStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOccurrence extends Model
{
    protected $fillable = [
        'reference',
        'production_obligation_id',
        'period_key',
        'reference_date',
        'period_start',
        'period_end',
        'deadline_at',
        'structure_id',
        'responsible_id',
        'task_id',
        'status',
        'deposited_at',
        'submitted_at',
        'validated_at',
        'transmitted_at',
        'closed_at',
        'cancelled_at',
        'delay_justification',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionOccurrenceStatus::class,
            'reference_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'deadline_at' => 'datetime',
            'deposited_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'transmitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ProductionObligation::class, 'production_obligation_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductionFile::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ProductionValidation::class)->orderBy('step_order');
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(ProductionTransmission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionEvent::class);
    }

    public function isPastDeadline(?CarbonInterface $at = null): bool
    {
        if (! $this->deadline_at) {
            return false;
        }

        return $this->deadline_at->lt($at ?? now());
    }

    /** @return list<string> */
    public function delayFlags(?CarbonInterface $at = null): array
    {
        $at ??= now();
        $flags = [];

        if (! $this->deadline_at || ! $this->isPastDeadline($at)) {
            return $flags;
        }

        if (! $this->deposited_at) {
            $flags[] = 'non_deposee';
        } elseif ($this->deposited_at->gt($this->deadline_at)) {
            $flags[] = 'deposee_en_retard';
        }

        if ($this->validated_at && $this->validated_at->gt($this->deadline_at)) {
            $flags[] = 'validee_en_retard';
        }

        if ($this->transmitted_at && $this->transmitted_at->gt($this->deadline_at)) {
            $flags[] = 'transmise_en_retard';
        } elseif (! $this->transmitted_at && $this->status !== ProductionOccurrenceStatus::Transmise) {
            $flags[] = 'non_transmise';
        }

        return $flags;
    }
}
