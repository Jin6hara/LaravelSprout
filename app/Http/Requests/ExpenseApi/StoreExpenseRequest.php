<?php

/**
 * 経費の新規登録フォームのバリデーションリクエスト。
 */
namespace App\Http\Requests\ExpenseApi;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseTripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'expense_report_id' => ['required', 'exists:expense_reports,id'],
            'expense_date'      => ['required', 'date'],
            'seq'               => ['required', 'integer', 'min:0'],
            'station_from'      => ['nullable', 'string', 'max:255'],
            'station_to'        => ['nullable', 'string', 'max:255'],
            'note'              => ['nullable', 'string'],
            'cost'              => ['nullable', 'integer', 'min:0'],
            'trip_type'         => ['required', Rule::in(ExpenseTripType::values())],
            'category'          => ['required', Rule::in(ExpenseCategory::values())],
            'commuter_pass_id'  => ['nullable', 'exists:commuter_passes,id'],
        ];
    }
}
