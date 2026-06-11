<?php

/**
 * 管理者による欠勤・休暇の一括更新リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\LeaveManage;

use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateLeaveManageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1'],
            'items.*.id'           => ['required', 'integer', 'exists:leaves,id'],
            'items.*.user_id'      => ['required', 'integer', 'exists:users,id'],
            'items.*.start_date'   => ['required', 'date'],
            'items.*.end_date'     => ['nullable', 'date', 'after_or_equal:items.*.start_date'],
            'items.*.reason'       => ['nullable', 'string'],
            'items.*.kind'         => ['required', Rule::in(LeaveKind::values())],
            'items.*.excused'      => ['required', Rule::in(LeaveExcused::managedValues())],
            'items.*.special_type' => ['nullable', 'string', 'max:100'],
            'items.*.time_start'   => ['nullable', 'date_format:H:i', 'required_with:items.*.time_end'],
            'items.*.time_end'     => ['nullable', 'date_format:H:i', 'required_with:items.*.time_start'],
            'items.*.handle_type'  => ['nullable', 'string'],
            'items.*.status'       => ['required', Rule::in(LeaveStatus::values())],
            'items.*.generated_shift_action' => ['nullable', Rule::in(['detach', 'delete'])],
            'items.*.inactive_generated_shift_action' => ['nullable', Rule::in(['detach', 'delete'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            foreach ($v->getData()['items'] ?? [] as $it) {
                if (!empty($it['time_start']) && !empty($it['time_end'])) {
                    $ts = Carbon::createFromFormat('H:i', $it['time_start']);
                    $te = Carbon::createFromFormat('H:i', $it['time_end']);
                    if ($te->lessThanOrEqualTo($ts)) {
                        $v->errors()->add('time_end', "ID {$it['id']}: End time must be after Start time.");
                    }
                }
            }
        });
    }
}
