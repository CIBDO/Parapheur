<?php

namespace App\Http\Requests;

use App\Enums\AppointmentMeetingMode;
use App\Enums\AppointmentOriginType;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('appointments.update')
            || $this->user()?->can('appointments.manage_requests')
            || $this->user()?->can('admin.access');
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'appointment_type_id' => ['nullable', 'exists:appointment_types,id'],
            'requested_date' => ['nullable', 'date'],
            'requested_start_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'requested_end_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'proposed_availabilities' => ['nullable', 'array'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_mode' => ['nullable', Rule::enum(AppointmentMeetingMode::class)],
            'visio_url' => ['nullable', 'string', 'max:500'],
            'external_address' => ['nullable', 'string', 'max:500'],
            'external_host_organization' => ['nullable', 'string', 'max:255'],
            'external_contact' => ['nullable', 'string', 'max:255'],
            'logistics_info' => ['nullable', 'string'],
            'origin_type' => ['nullable', Rule::enum(AppointmentOriginType::class)],
            'requester_user_id' => ['nullable', 'exists:users,id'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_organization' => ['nullable', 'string', 'max:255'],
            'requester_position' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_phone' => ['nullable', 'string', 'max:50'],
            'director_id' => ['nullable', 'exists:users,id'],
            'secretariat_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'priority' => ['nullable', Rule::enum(DocumentPriority::class)],
            'confidentiality' => ['nullable', Rule::enum(DocumentConfidentiality::class)],
            'blocks_calendar' => ['nullable', 'boolean'],
            'context_note' => ['nullable', 'string'],
            'points_to_discuss' => ['nullable', 'string'],
            'decision_note' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'string'],
            'result_summary' => ['nullable', 'string'],
            'linked_document_id' => ['nullable', 'exists:documents,id'],
            'force' => ['nullable', 'boolean'],
            'participant_ids' => ['nullable', 'array'],
            'participants' => ['nullable', 'array'],
        ];
    }
}
