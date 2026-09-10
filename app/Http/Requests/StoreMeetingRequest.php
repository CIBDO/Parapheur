<?php

namespace App\Http\Requests;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meetings.manage')
            || $this->user()?->can('meetings.create')
            || $this->user()?->can('admin.access');
    }

    public function rules(): array
    {
        return [
            'title' => ['required_without:object', 'nullable', 'string', 'max:255'],
            'object' => ['required_without:title', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_type_id' => ['nullable', 'exists:meeting_types,id'],
            'meeting_date' => ['required', 'date'],
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
            'confidentiality' => ['nullable', Rule::enum(DocumentConfidentiality::class)],
            'priority' => ['nullable', Rule::enum(DocumentPriority::class)],
            'is_recurring' => ['nullable', 'boolean'],
            'parent_meeting_id' => ['nullable', 'exists:meetings,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['exists:users,id'],
            'participants' => ['nullable', 'array'],
            'participants.*.participation_type' => ['nullable', 'in:interne,externe'],
            'participants.*.user_id' => ['nullable', 'exists:users,id'],
            'participants.*.external_name' => ['nullable', 'string', 'max:255'],
            'participants.*.external_function' => ['nullable', 'string', 'max:255'],
            'participants.*.external_structure' => ['nullable', 'string', 'max:255'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
            'participants.*.phone' => ['nullable', 'string', 'max:50'],
            'participants.*.role' => ['nullable', 'string', 'max:50'],
            'participants.*.is_required' => ['nullable', 'boolean'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['exists:documents,id'],
            'agenda_items' => ['nullable', 'array'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:255'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', Rule::in(['daily', 'weekly', 'monthly', 'custom'])],
            'recurrence.interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'recurrence.weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
            'recurrence.ends_on' => ['nullable', 'date', 'after_or_equal:meeting_date'],
            'recurrence.occurrences_limit' => ['nullable', 'integer', 'min:2', 'max:26'],
            'recurrence.starts_on' => ['nullable', 'date'],
        ];
    }
}
