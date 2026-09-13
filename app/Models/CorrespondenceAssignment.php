<?php

namespace App\Models;

use App\Enums\CorrespondenceAssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'from_user_id',
        'to_user_id',
        'to_structure_id',
        'action_id',
        'instruction_id',
        'status',
        'instruction_text',
        'due_date',
        'taken_charge_at',
        'processed_at',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'status' => CorrespondenceAssignmentStatus::class,
            'due_date' => 'date',
            'taken_charge_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function toStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'to_structure_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceAssignmentAction::class, 'action_id');
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }
}
