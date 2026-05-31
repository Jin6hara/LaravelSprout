<?php

namespace App\Http\Requests\RoleManage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name'    => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('roles')->where('guard_name', 'web')->ignore($role->id)],
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
