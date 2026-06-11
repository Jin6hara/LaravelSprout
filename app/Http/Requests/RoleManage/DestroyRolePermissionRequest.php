<?php

/**
 * ロールに付与されたパーミッション削除リクエストの認可とバリデーションを定義する。
 */
namespace App\Http\Requests\RoleManage;

use Illuminate\Foundation\Http\FormRequest;

class DestroyRolePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
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
