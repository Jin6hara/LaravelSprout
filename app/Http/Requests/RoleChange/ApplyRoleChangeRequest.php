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
        $role    = $this->input('role');
        $isAdmin = in_array($role, ['admin', 'super_admin']);

        return [
            'role'                    => ['required', 'in:general,admin,super_admin'],
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

            if ($role === 'general' && !empty($scopes)) {
                $v->errors()->add('scopes', '一般ユーザーには管理範囲を設定できません。');
                return;
            }

            if (!is_array($scopes)) {
                return;
            }

            $seen = [];
            foreach ($scopes as $i => $scope) {
                $key = ($scope['district_id'] ?? '') . '-' . ($scope['department_id'] ?? '');
                if (isset($seen[$key])) {
                    $v->errors()->add("scopes.$i", '同じ地区・部署の組み合わせが重複しています。');
                }
                $seen[$key] = true;
            }
        });
    }

    public function messages(): array
    {
        return [
            'district_id.required'          => '所属地区を選択してください。',
            'district_id.exists'            => '選択された所属地区は存在しません。',
            'department_id.required'        => '所属部署を選択してください。',
            'department_id.exists'          => '選択された所属部署は存在しません。',
            'scopes.required'               => '管理地区・部署を1件以上追加してください。',
            'scopes.min'                    => '管理地区・部署を1件以上追加してください。',
            'scopes.*.district_id.required' => '管理範囲の地区を選択してください。',
            'scopes.*.district_id.exists'   => '選択された管理地区は存在しません。',
            'scopes.*.department_id.required' => '管理範囲の部署を選択してください。',
            'scopes.*.department_id.exists' => '選択された管理部署は存在しません。',
        ];
    }
}
