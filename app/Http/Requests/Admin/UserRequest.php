<?php

/**
 * 管理者によるユーザー新規登録フォームのバリデーションリクエスト。
 */
namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'family_name'   => ['required', 'string', 'max:255'],
            'first_name'    => ['required', 'string', 'max:255'],
            'name_in_kana'  => ['nullable', 'string', 'max:255'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'gender'        => ['required', Rule::in(Gender::values())],
            'employee_code' => ['required', 'digits:6', 'unique:users'],
            'phone_number'  => ['nullable', 'string', 'max:15'],
            'address'       => ['nullable', 'string', 'max:255'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'type_name'     => ['nullable', 'string', 'max:100'],
            'type_code'     => ['nullable', 'string', 'max:50'],
            'note'          => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.required' => 'Employee code is required.',
            'employee_code.digits'   => 'Employee code must be exactly 6 digits.',
            'employee_code.unique'   => 'This employee code is already in use.',
        ];
    }
}
