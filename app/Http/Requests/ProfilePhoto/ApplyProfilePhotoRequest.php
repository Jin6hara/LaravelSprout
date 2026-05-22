<?php

namespace App\Http\Requests\ProfilePhoto;

use Illuminate\Foundation\Http\FormRequest;

class ApplyProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'photo'  => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'delete' => ['nullable'],
        ];
    }
}
