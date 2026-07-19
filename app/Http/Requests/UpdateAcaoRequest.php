<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Governo e Órgão
            'orgao_governo_id' => [
                'sometimes',
                'integer',
                Rule::exists('orgaos_governo', 'id'),
            ],

            // Categorias
            'categoria_investimento_id' => [
                'sometimes',
                'integer',
                Rule::exists('categorias_investimento', 'id'),
            ],
            'tipo_acao_id' => [
                'sometimes',
                'integer',
                Rule::exists('tipos_acao', 'id'),
            ],

            // Detalhes da Ação
            'titulo' => ['sometimes', 'string', 'max:255'],
            'numero_sei' => ['nullable', 'string', 'max:50'],
            'id_municipio' => [
                'sometimes',
                'integer',
                Rule::exists('municipios', 'id_municipio'),
            ],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'ano' => ['sometimes', 'integer', 'digits:4', 'min:1900', 'max:2050'],
            'status_id' => [
                'sometimes',
                'integer',
                Rule::exists('status_acao', 'id'),
            ],
            'liderancas' => [
                'nullable',
                'array',
                'max:10',
            ],
            'liderancas.*' => [
                'integer',
                Rule::exists('liderancas', 'id')->where(function ($query) {
                    $query->where('deputado_id', $this->user()->deputado_id)
                        ->where('id_municipio', $this->id_municipio);
                }),
            ],
            'observacao' => ['nullable', 'string', 'max:1000'],

            // Para update de status
            'observacao_mudanca' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'orgao_governo_id.exists' => 'Órgão selecionado não existe.',
            'categoria_investimento_id.exists' => 'Categoria selecionada não existe.',
            'tipo_acao_id.exists' => 'Tipo selecionado não existe.',
            'titulo.string' => 'O título deve ser um texto.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'numero_sei.string' => 'O número SEI deve ser um texto.',
            'numero_sei.max' => 'O número SEI não pode ter mais de 50 caracteres.',
            'id_municipio.exists' => 'Município não encontrado.',
            'valor.numeric' => 'O valor deve ser um número.',
            'valor.min' => 'O valor deve ser maior ou igual a zero.',
            'valor.max' => 'O valor não pode ser maior que 999.999.999.',
            'ano.integer' => 'O ano deve ser um número inteiro.',
            'ano.digits' => 'O ano deve ter 4 dígitos.',
            'ano.min' => 'O ano deve ser maior ou igual a 1900.',
            'ano.max' => 'O ano deve ser menor ou igual a 2050.',
            'status_id.exists' => 'Status selecionado não existe.',
            'liderancas.*.exists' => 'Uma ou mais lideranças não encontradas ou não pertencem a este deputado.',
            'observacao.string' => 'A observação deve ser um texto.',
            'observacao.max' => 'A observação não pode ter mais de 1000 caracteres.',
            'observacao_mudanca.string' => 'A observação da mudança deve ser um texto.',
            'observacao_mudanca.max' => 'A observação da mudança não pode ter mais de 500 caracteres.',
        ];
    }
}
