<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLiderancaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_municipio' => 'required|exists:municipios,id_municipio',
            'classificacao_id' => 'required|exists:classificacoes_lideranca,id',
            'funcao_id' => 'required|exists:cargos_lideranca,id',
            'nome' => 'required|string|max:255',
            'telefone' => 'nullable|string|max:20',
            'instagram' => 'nullable|string|max:50|regex:/^@?[a-zA-Z0-9._]+$/', // ⬅️ NOVO
            'data_nascimento' => 'nullable|date|after:1900-01-01|before:tomorrow',
            'alinhamento' => 'required|in:aliado,oposicao',
            'observacao' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'id_municipio.required' => 'O município é obrigatório.',
            'id_municipio.exists' => 'Município inválido.',

            'classificacao_id.required' => 'A classificação é obrigatória.',
            'classificacao_id.exists' => 'Classificação inválida.',

            'funcao_id.required' => 'A função/cargo é obrigatório.',
            'funcao_id.exists' => 'Função inválida.',

            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',

            'telefone.max' => 'O telefone não pode ter mais de 20 caracteres.',

            'instagram.max' => 'O usuário do Instagram não pode ter mais de 30 caracteres.',
            'instagram.regex' => 'O usuário do Instagram deve conter apenas letras, números, pontos e sublinhados.',

            'data_nascimento.date' => 'Data de nascimento inválida.',
            'data_nascimento.after' => 'Data de nascimento não pode ser anterior a 1900.',
            'data_nascimento.before' => 'Data de nascimento não pode ser no futuro.',

            'alinhamento.required' => 'O alinhamento é obrigatório.',
            'alinhamento.in' => 'Alinhamento inválido. Escolha entre: Aliado ou Oposição.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // ⬇️ NOVO: Sanitiza Instagram (remove @ se vier)
            if ($this->filled('instagram')) {
                $instagram = ltrim($this->instagram, '@');
                $this->merge(['instagram' => $instagram]);
            }
        });
    }
}
