<?php

namespace App\Http\Requests\ScheduleLine;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'     => ['nullable', 'exists:users,id'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'dow'         => ['nullable', 'integer', 'between:0,6'],
        ];
    }
}
