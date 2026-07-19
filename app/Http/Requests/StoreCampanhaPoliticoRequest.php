<?php

namespace App\Http\Requests;

use App\Models\CampanhaPolitico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampanhaPoliticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'nome_urna' => 'required|string|max:255',
            'numero_eleitoral' => 'required|string|max:10|regex:/^\d+$/',
            'cargo' => ['required', Rule::in(CampanhaPolitico::CARGOS)],
            'partido' => 'required|string|max:255',
            'federacao' => 'nullable|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'cor_principal' => ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'coordenador' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:20',
            'foto_url' => 'nullable|url:http,https|max:255',
            'ativo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',

            'nome_urna.required' => 'O nome de urna é obrigatório.',
            'nome_urna.max' => 'O nome de urna não pode ter mais de 255 caracteres.',

            'numero_eleitoral.required' => 'O número eleitoral é obrigatório.',
            'numero_eleitoral.regex' => 'O número eleitoral deve conter apenas dígitos.',
            'numero_eleitoral.max' => 'O número eleitoral não pode ter mais de 10 dígitos.',

            'cargo.required' => 'O cargo é obrigatório.',
            'cargo.in' => 'Cargo inválido. Escolha entre: estadual, federal, senador, governador ou presidente.',

            'partido.required' => 'O partido é obrigatório.',
            'partido.max' => 'O partido não pode ter mais de 255 caracteres.',

            'cor_principal.regex' => 'A cor principal deve ser um hexadecimal válido (ex.: #FFF ou #1A2B3C).',

            'telefone.max' => 'O telefone não pode ter mais de 20 caracteres.',

            'foto_url.url' => 'A URL da foto é inválida. Use um endereço http ou https.',

            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        ];
    }
}
