<?php

/**
 * ユーザー個別ロール更新リクエストの認可とバリデーションを定義する。
 */
namespace App\Http\Requests\RoleManage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModelRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Confirmation is required before changing role management data.',
        ];
    }
}
