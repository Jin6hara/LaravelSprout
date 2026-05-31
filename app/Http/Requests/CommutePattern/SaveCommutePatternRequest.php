<?php

namespace App\Http\Requests\CommutePattern;

use App\Enums\DayOfWeek;
use App\Enums\ExpenseTripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommutePatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pattern_id' => ['nullable', 'integer', 'exists:commute_patterns,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'closest_station' => ['required', 'string', 'max:255'],
            'train_line' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'reason' => ['nullable', 'string'],

            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.dow' => ['required', Rule::in(DayOfWeek::values())],
            'rows.*.seq' => ['nullable', 'integer', 'min:0'],
            'rows.*.station_from' => ['nullable', 'string', 'max:255'],
            'rows.*.station_to' => ['nullable', 'string', 'max:255'],
            'rows.*.note' => ['nullable', 'string'],
            'rows.*.cost' => ['required', 'integer', 'min:0'],
            'rows.*.trip_type' => ['required', Rule::in(ExpenseTripType::values())],
        ];
    }
}
