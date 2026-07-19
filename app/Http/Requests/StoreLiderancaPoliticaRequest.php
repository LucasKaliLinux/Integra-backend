<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiderancaPoliticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_municipio' => 'required|integer|exists:municipios,id_municipio',
            'aliados' => 'nullable|array',
            'aliados.*' => 'integer',
            'oposicao' => 'nullable|array',
            'oposicao.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'id_municipio.required' => 'O município é obrigatório.',
            'id_municipio.exists' => 'Município inválido.',
            'aliados.array' => 'O campo aliados deve ser um array.',
            'oposicao.array' => 'O campo oposição deve ser um array.',
            'aliados.*.numeric' => 'ID de candidato aliado inválido.',
            'oposicao.*.numeric' => 'ID de candidato oposição inválido.',
        ];
    }
}
