<?php

/**
 * ロール変更申請リクエストのバリデーションと管理スコープの重複チェックを定義する。
 */
namespace App\Http\Requests\RoleChange;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyRoleChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $role    = $this->input('role');
        $isAdmin = in_array($role, ['admin', 'super_admin']);

        return [
            'role'                    => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'reason'                  => ['required', 'string', 'max:2000'],
            'district_id'             => ['required', 'integer', 'exists:districts,id'],
            'department_id'           => ['required', 'integer', 'exists:departments,id'],
            'scopes'                  => $isAdmin ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'scopes.*.district_id'    => ['required', 'integer', 'exists:districts,id'],
            'scopes.*.department_id'  => ['required', 'integer', 'exists:departments,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $role   = $this->input('role');
            $scopes = $this->input('scopes') ?? [];

            if (!in_array($role, ['admin', 'super_admin'], true) && !empty($scopes)) {
                $v->errors()->add('scopes', 'Only admin and super_admin roles can have management scopes.');
                return;
            }

            if (!is_array($scopes)) {
                return;
            }

            $seen = [];
            foreach ($scopes as $i => $scope) {
                $key = ($scope['district_id'] ?? '') . '-' . ($scope['department_id'] ?? '');
                if (isset($seen[$key])) {
                    $v->errors()->add("scopes.$i", 'The same district and department combination is duplicated.');
                }
                $seen[$key] = true;
            }
        });
    }

    public function messages(): array
    {
        return [
            'role.exists'                   => 'The selected role does not exist.',
            'district_id.required'          => 'Please select a district.',
            'district_id.exists'            => 'The selected district does not exist.',
            'department_id.required'        => 'Please select a department.',
            'department_id.exists'          => 'The selected department does not exist.',
            'scopes.required'               => 'Please add at least one management scope.',
            'scopes.min'                    => 'Please add at least one management scope.',
            'scopes.*.district_id.required' => 'Please select a district for each management scope.',
            'scopes.*.district_id.exists'   => 'The selected management district does not exist.',
            'scopes.*.department_id.required' => 'Please select a department for each management scope.',
            'scopes.*.department_id.exists' => 'The selected management department does not exist.',
        ];
    }
}
