<?php

/**
 * 欠勤・休暇の新規登録リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\Leave;

use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['nullable', 'integer'],
            'user_lookup'  => ['nullable', 'string', 'max:255'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'kind'         => ['required', Rule::in(LeaveKind::storeValues())],
            'excused'      => ['nullable', Rule::in(LeaveExcused::values())],
            'special_type' => ['nullable', 'string', 'max:100'],
            'reason'       => ['nullable', 'string'],
            'time_start'   => ['nullable', 'date_format:H:i'],
            'time_end'     => ['nullable', 'date_format:H:i', 'after:time_start'],
            'status'       => ['nullable', Rule::in(LeaveStatus::storeValues())],
            'redirect_to'  => ['nullable', 'string', 'max:2048'],
        ];
    }
}
