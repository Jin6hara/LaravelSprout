<?php

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
