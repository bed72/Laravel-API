<?php

namespace App\Features\Authentication\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordResetConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'uid' => [
                'required',
            ],
            'token' => [
                'required',
            ],
            'new_password' => [
                'required',
                'string',
                'min:8',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uid.required' => 'O campo uid é obrigatório.',
            'token.required' => 'O campo token é obrigatório.',
            'new_password.required' => 'O campo nova senha é obrigatório.',
            'new_password.string' => 'A nova senha deve ser um texto válido.',
            'new_password.min' => 'Você deve fornecer uma senha com pelo menos 8 caracteres.',
        ];
    }
}
