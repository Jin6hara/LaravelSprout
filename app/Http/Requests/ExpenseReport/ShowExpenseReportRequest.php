<?php

/**
 * 経費精算レポートの表示対象年月指定フォームのバリデーションリクエスト。
 */
namespace App\Http\Requests\ExpenseReport;

use Illuminate\Foundation\Http\FormRequest;

class ShowExpenseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
