<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveApplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id'    => ['nullable', 'exists:users,id'],
            'dates'      => ['required', 'array', 'min:1'],
            'dates.*'    => ['required', 'date'], // 必要なら after_or_equal:today 等を追加
            'reason'     => ['nullable', 'string'],
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
