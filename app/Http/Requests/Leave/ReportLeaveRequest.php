<?php

/**
 * 欠勤報告（事後届）提出リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class ReportLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason'      => ['required', 'string', 'max:1000'],
            'handle_type' => ['required', 'in:apply_alp,no_alp,clinic,sick_child,special_leave,menstrual_leave'],
            'attachment'  => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ];
    }
}
