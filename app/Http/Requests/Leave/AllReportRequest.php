<?php

/**
 * 全ユーザーの欠勤レポート一覧取得リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\Leave;

use App\Enums\LeaveKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status'  => ['nullable', Rule::in(['required', 'submitted', 'all'])],
            'kind'    => ['nullable', Rule::in([...LeaveKind::absenceReportValues(), 'all'])],
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
