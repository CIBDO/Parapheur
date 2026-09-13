<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGenerationEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'template_version_id',
        'correspondence_id',
        'transmission_slip_id',
        'generated_document_id',
        'user_id',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function transmissionSlip(): BelongsTo
    {
        return $this->belongsTo(TransmissionSlip::class);
    }

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'generated_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
