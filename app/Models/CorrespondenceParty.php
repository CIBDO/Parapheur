<?php

namespace App\Models;

use App\Enums\CorrespondencePartyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceParty extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'correspondent_id',
        'role',
        'name',
        'function',
        'organization',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'role' => CorrespondencePartyRole::class,
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function correspondent(): BelongsTo
    {
        return $this->belongsTo(Correspondent::class);
    }
}
