<?php

namespace App\Models;

use App\Enums\ExpectedAction;
use App\Enums\ParapheurFolder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTransmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'workflow_instance_id',
        'from_user_id',
        'to_user_id',
        'expected_action',
        'folder',
        'status',
        'message',
        'seen_at',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_action' => ExpectedAction::class,
            'folder' => ParapheurFolder::class,
            'seen_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }
}
