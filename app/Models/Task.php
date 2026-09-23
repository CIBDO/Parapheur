<?php

namespace App\Models;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    protected $fillable = [
        'reference',
        'parent_id',
        'instruction_id',
        'created_by',
        'assignee_id',
        'structure_id',
        'validator_id',
        'taken_charge_by',
        'title',
        'description',
        'status',
        'priority',
        'confidentiality',
        'source_kind',
        'source_type',
        'source_id',
        'progress',
        'starts_at',
        'due_at',
        'taken_charge_at',
        'completed_at',
        'validated_at',
        'cancelled_at',
        'cancel_reason',
        'is_personal',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
            'source_kind' => TaskSource::class,
            'progress' => 'integer',
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'taken_charge_at' => 'datetime',
            'completed_at' => 'datetime',
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_personal' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function takenChargeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_charge_by');
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_contributors')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function contributorRows(): HasMany
    {
        return $this->hasMany(TaskContributor::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderByDesc('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->orderByDesc('created_at');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TaskHistory::class)->orderByDesc('created_at');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class)->orderByDesc('completed_at');
    }

    public function latestCompletion(): HasOne
    {
        return $this->hasOne(TaskCompletion::class)->latestOfMany('completed_at');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(TaskValidation::class)->orderByDesc('decided_at');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(TaskReminder::class);
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(TaskDocument::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'task_documents')
            ->withPivot(['role', 'submitted_to_ged', 'submitted_to_ged_at', 'note', 'linked_by'])
            ->withTimestamps();
    }

    public function isOverdue(): bool
    {
        if (! $this->due_at || ! $this->status?->isOpen()) {
            return false;
        }

        return $this->due_at->isPast();
    }

    public function dueBucket(): ?string
    {
        if (! $this->due_at || ! $this->status?->isOpen()) {
            return null;
        }

        $today = now()->startOfDay();
        $due = $this->due_at->copy()->startOfDay();

        if ($due->lt($today)) {
            return 'en_retard';
        }
        if ($due->equalTo($today)) {
            return 'aujourdhui';
        }
        if ($due->lte($today->copy()->addDays(3))) {
            return 'bientot';
        }

        return 'a_venir';
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('task.view_all') || $user->can('task.manage') || $user->can('admin.access')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('assignee_id', $user->id)
                ->orWhere('validator_id', $user->id)
                ->orWhereHas('contributors', fn (Builder $c) => $c->where('users.id', $user->id));

            if ($user->can('task.view_team') && $user->structure_id) {
                $q->orWhere('structure_id', $user->structure_id);
            }
        })->where(function (Builder $q) use ($user) {
            $q->where('status', '!=', TaskStatus::Brouillon->value)
                ->orWhere('created_by', $user->id);
        });
    }

    public function scopeMine(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('assignee_id', $user->id)
                ->orWhereHas('contributors', fn (Builder $c) => $c->where('users.id', $user->id));
        })->where('status', '!=', TaskStatus::Brouillon->value);
    }

    public function scopeAssignedBy(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id);
    }

    public function scopeToValidate(Builder $query, User $user): Builder
    {
        return $query->where('status', TaskStatus::AValider->value)
            ->where(function (Builder $q) use ($user) {
                $q->where('validator_id', $user->id)
                    ->orWhere(function (Builder $inner) use ($user) {
                        $inner->whereNull('validator_id')->where('created_by', $user->id);
                    });
            });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::openValues())
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }
}
