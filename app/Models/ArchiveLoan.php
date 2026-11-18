<?php

namespace App\Models;

use App\Enums\ArchiveLoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'status',
        'box_id',
        'record_id',
        'structure_id',
        'requester_id',
        'validated_by',
        'purpose',
        'loan_days',
        'due_at',
        'validated_at',
        'prepared_at',
        'checked_out_at',
        'returned_at',
        'condition_out',
        'condition_in',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArchiveLoanStatus::class,
            'due_at' => 'date',
            'validated_at' => 'datetime',
            'prepared_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'box_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
