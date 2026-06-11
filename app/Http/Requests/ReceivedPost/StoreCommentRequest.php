<?php

/**
 * 受信投稿へのコメント新規作成リクエストのバリデーションを定義する。
 */
namespace App\Http\Requests\ReceivedPost;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }
}
