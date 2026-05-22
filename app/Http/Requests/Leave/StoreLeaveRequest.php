<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['required', 'exists:users,id'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'kind'         => ['required', 'in:paid,special,absence,late,other'],
            'excused'      => ['nullable', 'in:excused,unexcused,unknown'],
            'special_type' => ['nullable', 'string', 'max:100'],
            'reason'       => ['nullable', 'string'],
            'time_start'   => ['nullable', 'date_format:H:i'],
            'time_end'     => ['nullable', 'date_format:H:i', 'after:time_start'],
            'status'       => ['nullable', 'in:approved,pending,rejected'],
        ];
    }
}
