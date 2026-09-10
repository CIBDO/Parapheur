<?php

namespace App\Http\Requests;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'object' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_type_id' => ['nullable', 'exists:meeting_types,id'],
            'meeting_date' => ['sometimes', 'date'],
            'meeting_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'location' => ['nullable', 'string', 'max:255'],
            'visio_url' => ['nullable', 'string', 'max:500'],
            'chair_id' => ['nullable', 'exists:users,id'],
            'secretary_id' => ['nullable', 'exists:users,id'],
            'organizer_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'agenda' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'confidentiality' => ['nullable', Rule::enum(DocumentConfidentiality::class)],
            'priority' => ['nullable', Rule::enum(DocumentPriority::class)],
            'lock_version' => ['nullable', 'integer'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['exists:users,id'],
            'participants' => ['nullable', 'array'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['exists:documents,id'],
            'agenda_items' => ['nullable', 'array'],
        ];
    }
}
