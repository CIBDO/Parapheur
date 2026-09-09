<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'instruction_id',
        'user_id',
        'status',
        'body',
    ];

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
