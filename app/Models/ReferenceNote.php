<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenceNote extends Model
{
    protected $fillable = [
        'reference_id',
        'user_id',
        'body',
    ];

    public function reference(): BelongsTo
    {
        return $this->belongsTo(BibliographicReference::class, 'reference_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
