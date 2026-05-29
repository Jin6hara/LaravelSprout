<?php

namespace App\Http\Requests\EventAssign;

use App\Enums\EventStatus;
use App\Enums\ShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_date'       => ['required', 'date'],
            'original_user_id' => ['nullable', 'exists:users,id'],
            'Leave_type'       => ['nullable', 'string'],
            'title'            => ['nullable', 'string', 'max:255'],
            'school_name'      => ['nullable', 'string', 'max:255'],
            'start_time'       => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time'         => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'total_duration'   => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'Lesson'           => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'status'           => ['required', Rule::in(EventStatus::values())],
            'type'             => ['required', Rule::in(ShiftType::values())],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
