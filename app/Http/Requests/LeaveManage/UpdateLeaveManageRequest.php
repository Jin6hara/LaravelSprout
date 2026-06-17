<?php

/**
 * 管理者による欠勤・休暇の更新リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\LeaveManage;

use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveManageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['required', 'integer', 'exists:users,id'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason'       => ['nullable', 'string'],
            'kind'         => ['required', Rule::in(LeaveKind::values())],
            'excused'      => ['required', Rule::in(LeaveExcused::managedValues())],
            'special_type' => ['nullable', 'string', 'max:100'],
            'time_start'   => ['nullable', 'date_format:H:i', 'required_with:time_end'],
            'time_end'     => ['nullable', 'date_format:H:i', 'required_with:time_start'],
            'handle_type'  => ['nullable', 'string'],
            'status'       => ['required', Rule::in(LeaveStatus::values())],
            'generated_shift_action' => ['nullable', Rule::in(['detach', 'delete'])],
            'inactive_generated_shift_action' => ['nullable', Rule::in(['detach', 'delete'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $data = $v->getData();
            if (!empty($data['time_start']) && !empty($data['time_end'])) {
                $ts = Carbon::createFromFormat('H:i', $data['time_start']);
                $te = Carbon::createFromFormat('H:i', $data['time_end']);
                if ($te->lessThanOrEqualTo($ts)) {
                    $v->errors()->add('time_end', 'End time must be after Start time.');
                }
            }
        });
    }
}
