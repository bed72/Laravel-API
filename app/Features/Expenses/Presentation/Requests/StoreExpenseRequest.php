<?php

namespace App\Features\Expenses\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'description' => [
                'nullable',
                'string',
                'max:32',
            ],
        ];
    }
}
