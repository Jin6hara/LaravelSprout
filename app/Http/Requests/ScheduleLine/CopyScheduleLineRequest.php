<?php

namespace App\Http\Requests\ScheduleLine;

use Illuminate\Foundation\Http\FormRequest;

class CopyScheduleLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'effective_start' => ['required', 'date'],
            'effective_end'   => ['required', 'date', 'after_or_equal:effective_start'],
            'user_id'         => ['nullable', 'exists:users,id'],
            'handover_memo'   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
