<?php

namespace App\Http\Requests\ScheduleLine;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateScheduleLineRequest extends FormRequest
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

    public static function itemRules(array $raw): array
    {
        return [
            'user_id'         => ['nullable', 'exists:users,id'],
            'dow'             => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'school_name'     => ['required', 'string', 'max:255'],
            'start_time'      => ['required', 'date_format:H:i'],
            'end_time'        => ['required', 'date_format:H:i', function ($attr, $val, $fail) use ($raw) {
                if (!empty($raw['start_time']) && $val <= $raw['start_time']) {
                    $fail('end_time は start_time より後である必要があります。');
                }
            }],
            'effective_start' => ['required', 'date'],
            'effective_end'   => ['required', 'date', function ($attr, $val, $fail) use ($raw) {
                if (!empty($raw['effective_start']) && $val < $raw['effective_start']) {
                    $fail('effective_end は effective_start 以降である必要があります。');
                }
            }],
            'handover_memo'   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
