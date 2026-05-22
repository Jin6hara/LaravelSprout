<?php

namespace App\Http\Requests\LeaveApply;

use Illuminate\Foundation\Http\FormRequest;

class LeaveApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'dates'   => ['required', 'array', 'min:1'],
            'dates.*' => ['required', 'date'],
            'reason'  => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'dates.required' => '申請日を1つ以上入力してください。',
            'dates.*.date'   => '日付の形式が正しくありません。',
        ];
    }
}
