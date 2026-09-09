<?php

namespace App\Models;

use App\Enums\WorkflowActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'workflow_instance_id',
        'transmission_id',
        'actor_id',
        'delegator_id',
        'action_type',
        'from_status',
        'to_status',
        'comment',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => WorkflowActionType::class,
            'meta' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }
}
