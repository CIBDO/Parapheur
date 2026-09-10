<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentDocument extends Model
{
    protected $fillable = [
        'appointment_id',
        'document_id',
        'kind',
        'label',
        'sort_order',
        'attached_by',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function attacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by');
    }
}
