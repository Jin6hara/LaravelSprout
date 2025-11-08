<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Post::class) ?? true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['nullable', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'recipients'  => ['required', 'array', 'min:1'],
            'recipients.*' => ['integer', 'exists:users,id'],
            'attachments' => ['sometimes', 'array', 'max:10'],            // 最大10ファイル
            'attachments.*' => [
                'file',
                'max:10240', // 10MB/ファイル
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt,zip'
            ],
            'expires_at'   => ['nullable', 'date', 'after:now'],          // 期限（任意）
            'allow_replies' => ['required', 'boolean'],                   // 返信可否
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => '本文',
            'recipients' => '送信先',
            'attachments.*' => '添付ファイル',
        ];
    }
}
