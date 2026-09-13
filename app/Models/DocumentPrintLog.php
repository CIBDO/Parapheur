<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPrintLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'correspondence_id',
        'transmission_slip_id',
        'user_id',
        'printer_name',
        'page_count',
        'reason',
        'is_reprint',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'is_reprint' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function transmissionSlip(): BelongsTo
    {
        return $this->belongsTo(TransmissionSlip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
