<?php

namespace App\Http\Requests;

use App\Models\CargoLideranca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\ClassificacaoLideranca;

class StoreLiderancaRequest extends FormRequest
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
            'data_nascimento' => 'nullable|date|after:1900-01-01|before:tomorrow',
            'alinhamento' => 'required|in:aliado,oposicao',
            'observacao' => 'nullable|string'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Bloqueia criação de lideranças com classificação "politica"
            $cargo = CargoLideranca::find($this->funcao_id);
            
            if ($cargo && in_array($cargo->slug, ['prefeito', 'vereador'])) {
                $validator->errors()->add(
                    'funcao_id',
                    'Não é permitido cadastrar prefeitos ou vereadores manualmente.'
                );
            }
        });
    }
}