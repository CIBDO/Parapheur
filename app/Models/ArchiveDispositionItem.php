<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveDispositionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'disposition_request_id',
        'record_id',
        'box_id',
        'status',
        'notes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ArchiveDispositionRequest::class, 'disposition_request_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'box_id');
    }
}
