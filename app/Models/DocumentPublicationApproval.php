<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPublicationApproval extends Model
{
    protected $fillable = [
        'document_id',
        'step',
        'actor_id',
        'action',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
