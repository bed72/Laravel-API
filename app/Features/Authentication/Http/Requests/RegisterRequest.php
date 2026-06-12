<?php

namespace App\Features\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],
            'name' => [
                'required',
                'string',
                'min:1',
                'max:128',
            ],
            'password' => [
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
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.email' => 'Você deve fornecer um e-mail válido.',
            'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
            'name.required' => 'O campo nome é obrigatório.',
            'name.string' => 'O nome deve ser um texto válido.',
            'name.min' => 'O nome deve ter pelo menos 1 caractere.',
            'name.max' => 'O nome não pode ter mais de 128 caracteres.',
            'password.required' => 'O campo senha é obrigatório.',
            'password.string' => 'A senha deve ser um texto válido.',
            'password.min' => 'Você deve fornecer uma senha com pelo menos 8 caracteres.',
        ];
    }
}
