<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_correspondence_id',
        'target_correspondence_id',
        'link_type',
        'created_by',
    ];

    public function sourceCorrespondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'source_correspondence_id');
    }

    public function targetCorrespondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'target_correspondence_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
