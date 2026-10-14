<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StructureTypeDocumentProfile extends Model
{
    protected $fillable = [
        'structure_type_id',
        'ministry_line',
        'header_title',
        'append_structure_name',
        'footer_text',
        'address',
        'phone',
        'email',
        'republic_line',
        'motto',
        'signatory_left_label',
        'signatory_right_label',
    ];

    protected function casts(): array
    {
        return [
            'append_structure_name' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StructureType::class, 'structure_type_id');
    }
}
