<?php

namespace App\Http\Requests\DistrictDepartment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:districts,id'],
        ];
    }
}
