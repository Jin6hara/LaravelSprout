<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id'    => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'kind'       => ['required', 'in:paid,special,absence,late,other'],
            'excused'    => ['nullable', 'in:excused,unexcused,unknown'],
            'special_type' => ['nullable', 'string', 'max:100'],
            'reason'     => ['nullable', 'string'],
            'time_start' => ['nullable', 'date_format:H:i'],
            'time_end'   => ['nullable', 'date_format:H:i', 'after:time_start'],
            'status'     => ['nullable', 'in:approved,pending,rejected'],
        ];
    }
}
