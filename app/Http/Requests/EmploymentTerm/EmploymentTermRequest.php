<?php

/**
 * 雇用期間（種別・開始日・終了日）の登録・更新フォームのバリデーションリクエスト。
 */
namespace App\Http\Requests\EmploymentTerm;

use Illuminate\Foundation\Http\FormRequest;

class EmploymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'type_code'  => ['required', 'string', 'max:50'],
            'type_name'  => ['required', 'string', 'max:100'],
            'note'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
