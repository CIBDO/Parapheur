<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StructureDocumentProfile extends Model
{
    protected $fillable = [
        'structure_id',
        'ministry_line',
        'header_title',
        'footer_text',
        'address',
        'phone',
        'email',
        'republic_line',
        'motto',
        'signatory_left_label',
        'signatory_right_label',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
