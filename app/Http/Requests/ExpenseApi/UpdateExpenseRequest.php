<?php

namespace App\Http\Requests\ExpenseApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'station_from'     => ['nullable', 'string', 'max:255'],
            'station_to'       => ['nullable', 'string', 'max:255'],
            'note'             => ['nullable', 'string'],
            'cost'             => ['nullable', 'integer', 'min:0'],
            'trip_type'        => ['nullable', Rule::in(['round_trip', 'one_way'])],
            'category'         => ['nullable', Rule::in(['regular', 'irregular'])],
            'commuter_pass_id' => ['nullable', 'exists:commuter_passes,id'],
            'seq'              => ['nullable', 'integer', 'min:0'],
        ];
    }
}
