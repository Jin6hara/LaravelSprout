<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;

class UpdateUserFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $field      = $this->input('field');
        $targetUser = $this->route('user') ?? Auth::user();

        return match ($field) {
            'email'        => ['value' => "required|email|max:255|ascii|unique:users,email,{$targetUser->id}"],
            'phone_number' => ['value' => 'nullable|numeric|digits_between:10,11'],
            'address'      => ['value' => 'nullable|string|max:255'],
            'note'         => ['value' => 'nullable|string|max:1000'],
            default        => ['value' => 'nullable|string'],
        };
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first('value') ?? '不正な値です',
        ], 422));
    }
}
