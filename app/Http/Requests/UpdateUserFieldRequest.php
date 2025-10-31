<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserFieldRequest extends FormRequest
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
        $field = $this->input('field');
        $targetUser = $this->route('user') ?? Auth::user(); // ルートモデル or 自分

        return match ($field) {
            'email' => ['value' => "required|email|max:255|ascii|unique:users,email,{$targetUser->id}"],
            'phone_number' => ['value' => 'nullable|numeric|digits_between:10,11'],
            'address' => ['value' => 'nullable|string|max:255'],
            'note' => ['value' => 'nullable|string|max:1000'],
            default => ['value' => 'nullable|string'],
        };
    }

    ///コントローラーでの try-catch バリデーション処理を、FormRequest 側に移動したもの
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first('value')
                ?? '不正な値です',
        ], 422));
    }
}
