<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Qualquer usuário autenticado pode
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'A senha atual é obrigatória.',

            'new_password.required' => 'A nova senha é obrigatória.',
            'new_password.confirmed' => 'A confirmação da senha não confere.',

            'new_password.min' => 'A nova senha deve ter no mínimo :min caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'senha atual',
            'new_password' => 'nova senha',
        ];
    }
}
