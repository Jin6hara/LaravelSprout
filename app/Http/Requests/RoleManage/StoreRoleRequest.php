<?php

/**
 * 新規ロール作成リクエストの認可とバリデーションを定義する。
 */
namespace App\Http\Requests\RoleManage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('roles')->where('guard_name', 'web')],
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
