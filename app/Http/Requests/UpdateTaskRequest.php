<?php

namespace App\Http\Requests;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Task|null $task */
        $task = $this->route('task');

        return $task && $this->user()?->can('update', $task);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(DocumentPriority::class)],
            'confidentiality' => ['nullable', Rule::enum(DocumentConfidentiality::class)],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'validator_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'contributor_ids' => ['nullable', 'array'],
            'contributor_ids.*' => ['integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_personal' => ['nullable', 'boolean'],
        ];
    }
}
