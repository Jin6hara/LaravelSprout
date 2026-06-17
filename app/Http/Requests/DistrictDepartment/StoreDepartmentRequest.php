<?php

/**
 * 部署の新規登録フォームのバリデーションリクエスト。
 */
namespace App\Http\Requests\DistrictDepartment;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
