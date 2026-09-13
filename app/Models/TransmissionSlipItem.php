<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransmissionSlipItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transmission_slip_id',
        'correspondence_id',
        'reference',
        'object',
        'piece_count',
        'observations',
        'display_order',
    ];

    public function transmissionSlip(): BelongsTo
    {
        return $this->belongsTo(TransmissionSlip::class);
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }
}
