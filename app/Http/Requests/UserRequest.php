<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'family_name'   => 'required|string|max:255',
            'first_name'    => 'required|string|max:255',
            'name_in_kana'  => 'nullable|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8|confirmed',
            'gender'        => 'required|in:male,female,other,unknown',
            'employee_code' => 'required|digits:6|unique:users',
            'phone_number'  => 'nullable|string|max:15',
            'address'       => 'nullable|string|max:255',

            //下記はemployment_termsのためのフィールド
            'start_date'    => ['required', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'type_name'     => ['nullable', 'string', 'max:100'],
            'type_code'     => ['nullable', 'string', 'max:50'],
            'note'          => ['nullable', 'string', 'max:255'],
        ];
    }

    //これは追加のカスタマイズメッセージ
    public function messages()
    {
        return [
            'employee_code.required' => 'Employee code is required.',
            'employee_code.digits'   => 'Employee code must be exactly 5 digits.',
            'employee_code.unique'   => 'This employee code is already in use.',
        ];
    }
}
