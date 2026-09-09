<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'meeting_date',
        'meeting_time',
        'location',
        'chair_id',
        'created_by',
        'agenda',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'meeting_documents')
            ->withPivot(['sort_order', 'agenda_label'])
            ->withTimestamps();
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class);
    }
}
