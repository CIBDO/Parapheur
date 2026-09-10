<?php

namespace App\Models;

use App\Enums\CalendarUnavailabilityKind;
use App\Enums\DocumentConfidentiality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarUnavailability extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'kind',
        'title',
        'description',
        'start_at',
        'end_at',
        'location',
        'confidentiality',
        'blocks_calendar',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CalendarUnavailabilityKind::class,
            'confidentiality' => DocumentConfidentiality::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'blocks_calendar' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
