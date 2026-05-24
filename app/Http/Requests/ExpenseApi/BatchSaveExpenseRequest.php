<?php

namespace App\Http\Requests\ExpenseApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchSaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_id'               => ['required', 'exists:expense_reports,id'],

            'updates'                  => ['present', 'array'],
            'updates.*.id'             => ['required', 'integer', 'exists:expenses,id'],
            'updates.*.station_from'   => ['nullable', 'string', 'max:255'],
            'updates.*.station_to'     => ['nullable', 'string', 'max:255'],
            'updates.*.note'           => ['nullable', 'string'],
            'updates.*.cost'           => ['nullable', 'integer', 'min:0'],
            'updates.*.trip_type'      => ['nullable', Rule::in(['round_trip', 'one_way'])],
            'updates.*.seq'            => ['nullable', 'integer', 'min:0'],

            'creates'                  => ['present', 'array'],
            'creates.*.expense_date'   => ['required', 'date'],
            'creates.*.seq'            => ['required', 'integer', 'min:0'],
            'creates.*.station_from'   => ['nullable', 'string', 'max:255'],
            'creates.*.station_to'     => ['nullable', 'string', 'max:255'],
            'creates.*.note'           => ['nullable', 'string'],
            'creates.*.cost'           => ['nullable', 'integer', 'min:0'],
            'creates.*.trip_type'      => ['required', Rule::in(['round_trip', 'one_way'])],
            'creates.*.category'       => ['required', Rule::in(['regular', 'irregular'])],
        ];
    }
}
