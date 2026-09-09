<?php

namespace App\Models;

use App\Enums\ExpectedAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'step_order',
        'name',
        'role_name',
        'structure_id',
        'expected_action',
        'is_optional',
    ];

    protected function casts(): array
    {
        return [
            'expected_action' => ExpectedAction::class,
            'is_optional' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
