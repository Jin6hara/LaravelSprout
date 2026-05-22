<?php

namespace App\Http\Requests\CommuterPass;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommuterPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date_from'    => ['required', 'date'],
            'date_to'      => ['required', 'date', 'after_or_equal:date_from'],
            'station_from' => ['required', 'string', 'max:255'],
            'station_to'   => ['required', 'string', 'max:255'],
            'note'         => ['nullable', 'string'],
            'cost'         => ['nullable', 'integer', 'min:0'],
        ];
    }
}
