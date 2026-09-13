<?php

namespace App\Models;

use App\Enums\TransmissionSlipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransmissionSlip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'status',
        'from_structure_id',
        'to_structure_id',
        'nature',
        'observations',
        'document_id',
        'template_version_id',
        'created_by',
        'validated_by',
        'validated_at',
        'printed_at',
        'transmitted_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransmissionSlipStatus::class,
            'validated_at' => 'datetime',
            'printed_at' => 'datetime',
            'transmitted_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function fromStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'from_structure_id');
    }

    public function toStructure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'to_structure_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransmissionSlipItem::class);
    }
}
