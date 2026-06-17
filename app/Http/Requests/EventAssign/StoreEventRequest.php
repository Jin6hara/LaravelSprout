<?php

/**
 * イベントの新規登録フォームのバリデーションリクエスト。
 */
namespace App\Http\Requests\EventAssign;

use App\Enums\EventStatus;
use App\Enums\ShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_date'            => ['required', 'date'],
            'original_user_id'      => ['nullable', 'integer'],
            'original_user_lookup'  => ['nullable', 'string', 'max:255'],
            'Leave_type'            => ['nullable', 'string'],
            'title'                 => ['nullable', 'string', 'max:255'],
            'school_name'           => ['nullable', 'string', 'max:255'],
            'start_time'            => ['nullable', 'date_format:H:i'],
            'end_time'              => ['nullable', 'date_format:H:i'],
            'total_duration'        => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'Lesson'                => ['nullable', 'string'],
            'assigned_user_id'      => ['nullable', 'integer'],
            'assigned_user_lookup'  => ['nullable', 'string', 'max:255'],
            'status'                => ['nullable', Rule::in(EventStatus::values())],
            'type'                  => ['nullable', Rule::in(ShiftType::values())],
            'notes'                 => ['nullable', 'string'],
            'redirect_to'           => ['nullable', 'string', 'max:2048'],
        ];
    }
}
