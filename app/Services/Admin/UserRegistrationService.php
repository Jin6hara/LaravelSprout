<?php

/**
 * 管理者によるユーザー新規登録（雇用条件・ロール付与を含むトランザクション処理）を担うサービス。
 */
namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserRegistrationService
{
    public function register(array $data, int $districtId, int $departmentId): User
    {
        return DB::transaction(function () use ($data, $districtId, $departmentId) {
            $user = User::create([
                'family_name'     => $data['family_name'],
                'first_name'      => $data['first_name'],
                'name_in_kana'    => $data['name_in_kana'] ?? null,
                'email'           => $data['email'],
                'employee_code'   => $data['employee_code'],
                'password'        => Hash::make($data['password']),
                'gender'          => $data['gender'],
                'profile_picture' => null,
                'note'            => 'こんにちは、' . $data['first_name'] . 'です。',
                'district_id'     => $districtId,
                'department_id'   => $departmentId,
            ]);

            $user->employmentTerms()->create([
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'] ?? null,
                'type_name'  => $data['type_name'] ?? '正社員',
                'type_code'  => $data['type_code'] ?? 'full_time',
                'note'       => $data['note'] ?? null,
            ]);

            $user->assignRole('general');

            return $user;
        });
    }
}
