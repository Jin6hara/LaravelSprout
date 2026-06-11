<?php

/**
 * 管理者による欠勤・休暇の新規作成リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\LeaveManage;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveManageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
        ];
    }
}
