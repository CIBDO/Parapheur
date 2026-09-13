<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'dispatched_at',
        'method',
        'tracking_number',
        'dispatched_by',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
