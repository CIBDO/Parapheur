<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionRecipient extends Model
{
    protected $fillable = [
        'instruction_id',
        'user_id',
        'structure_id',
    ];

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
