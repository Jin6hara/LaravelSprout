<?php

namespace App\Http\Requests\RoleManage;

use Illuminate\Foundation\Http\FormRequest;

class StoreModelPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'confirm'       => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Confirmation is required before changing role management data.',
        ];
    }
}
