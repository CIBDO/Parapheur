<?php

namespace App\Http\Requests;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\TaskSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('task.create')
            || $this->user()?->can('task.manage')
            || $this->user()?->can('admin.access')
            || false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'validator_id' => ['nullable', 'exists:users,id'],
            'priority' => ['nullable', Rule::enum(DocumentPriority::class)],
            'confidentiality' => ['nullable', Rule::enum(DocumentConfidentiality::class)],
            'due_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'instruction_id' => ['nullable', 'exists:instructions,id'],
            'parent_id' => ['nullable', 'exists:tasks,id'],
            'contributor_ids' => ['nullable', 'array'],
            'contributor_ids.*' => ['integer', 'exists:users,id'],
            'as_draft' => ['nullable', 'boolean'],
            'is_personal' => ['nullable', 'boolean'],
            'source_kind' => ['nullable', Rule::enum(TaskSource::class)],
            'source_type' => ['nullable', 'string', 'max:255'],
            'source_id' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
