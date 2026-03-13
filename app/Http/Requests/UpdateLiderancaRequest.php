<?php

namespace App\Http\Requests;

use App\Models\CargoLideranca;
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
            'id_municipio'     => 'required|exists:municipios,id_municipio',
            'classificacao_id' => 'required|exists:classificacoes_lideranca,id',
            'funcao_id'        => 'required|exists:cargos_lideranca,id',
            'nome'             => 'required|string|max:255',
            'telefone'         => 'nullable|string|max:20',
            'data_nascimento'  => 'nullable|date|after:1900-01-01|before:tomorrow',
            'alinhamento'      => 'required|in:aliado,oposicao',
            'observacao'       => 'nullable|string',
        ];
    }
}