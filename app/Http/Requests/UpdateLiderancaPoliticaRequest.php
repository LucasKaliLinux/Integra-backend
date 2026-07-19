<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLiderancaPoliticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Campos NÃO editáveis (removidos):
            // - id_municipio
            // - classificacao_id
            // - funcao_id
            // - nome

            // Apenas campos editáveis para políticos:
            'telefone' => 'nullable|string|max:20',
            'alinhamento' => 'required|in:aliado,oposicao',
            'observacao' => 'nullable|string',
        ];
    }
}
