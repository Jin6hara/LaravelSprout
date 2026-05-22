<?php

namespace App\Http\Requests\RoleChange;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRoleChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'role'   => ['required', 'in:general,admin,super_admin'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
