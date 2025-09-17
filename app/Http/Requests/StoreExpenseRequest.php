<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_report_id' => ['required', 'exists:expense_reports,id'],
            'expense_date'      => ['required', 'date'],
            'station_from'      => ['nullable', 'string', 'max:191'],
            'station_to'        => ['nullable', 'string', 'max:191'],
            'note'              => ['nullable', 'string'],
            'cost'              => ['required', 'integer', 'min:0'],
            'trip_type'         => ['required', 'in:round_trip,one_way'],
            'category'          => ['required', 'in:regular,irregular'],
            'commuter_pass_id'  => ['nullable', 'exists:commuter_passes,id'],
        ];
    }
}
