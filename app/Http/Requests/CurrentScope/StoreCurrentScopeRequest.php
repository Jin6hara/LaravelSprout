<?php

/**
 * 現在の管理スコープ（scope_id）切り替え時のバリデーションリクエスト。
 */
namespace App\Http\Requests\CurrentScope;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrentScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'scope_id' => ['required', 'integer'],
        ];
    }
}
