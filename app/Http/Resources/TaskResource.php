<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Task */
class TaskResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'parent_id' => $this->parent_id,
            'instruction_id' => $this->instruction_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value ?? $this->priority,
            'confidentiality' => $this->confidentiality?->value ?? $this->confidentiality,
            'source_kind' => $this->source_kind?->value ?? $this->source_kind,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'progress' => $this->progress,
            'starts_at' => optional($this->starts_at)?->toIso8601String(),
            'due_at' => optional($this->due_at)?->toIso8601String(),
            'taken_charge_at' => optional($this->taken_charge_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'validated_at' => optional($this->validated_at)?->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'is_personal' => (bool) $this->is_personal,
            'is_overdue' => $this->isOverdue(),
            'due_bucket' => $this->dueBucket(),
            'tags' => $this->tags,
            'created_by' => $this->created_by,
            'assignee_id' => $this->assignee_id,
            'structure_id' => $this->structure_id,
            'validator_id' => $this->validator_id,
            'assignee' => $this->whenLoaded('assignee'),
            'creator' => $this->whenLoaded('creator'),
            'validator' => $this->whenLoaded('validator'),
            'structure' => $this->whenLoaded('structure'),
            'instruction' => $this->whenLoaded('instruction'),
            'contributors' => $this->whenLoaded('contributors'),
            'subtasks' => $this->whenLoaded('subtasks'),
            'comments' => $this->whenLoaded('comments'),
            'attachments' => $this->whenLoaded('attachments'),
            'histories' => $this->whenLoaded('histories'),
            'completions' => $this->whenLoaded('completions'),
            'validations' => $this->whenLoaded('validations'),
            'dependencies' => $this->whenLoaded('dependencies'),
            'document_links' => $this->whenLoaded('documentLinks'),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
