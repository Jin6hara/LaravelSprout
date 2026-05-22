<?php

namespace App\Http\Requests\ScheduleDetail;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateScheduleDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
        ];
    }

    public static function itemRules(): array
    {
        return [
            'lesson_code'     => ['required', 'string', 'max:255'],
            'note'            => ['nullable', 'string', 'max:2000'],
            'detail_note'     => ['nullable', 'string', 'max:2000'],
            'start_time'      => ['required', 'date_format:H:i'],
            'effective_start' => ['required', 'date'],
            'effective_end'   => ['nullable', 'date', 'after_or_equal:effective_start'],
        ];
    }
}
