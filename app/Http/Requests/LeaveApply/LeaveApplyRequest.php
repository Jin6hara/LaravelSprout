<?php

/**
 * 有給・特別休暇の申請リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\LeaveApply;

use App\Enums\LeaveKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type'    => ['nullable', Rule::in(LeaveKind::applicationValues())],
            'special_type' => ['nullable', 'string', 'max:100'],
            'attachment'   => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
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
