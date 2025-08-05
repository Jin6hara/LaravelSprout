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
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'gender'   => 'required|in:male,female,other,unknown',
            'employee_code' => 'required|digits:5|unique:users',
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
