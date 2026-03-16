<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\OrgaoGoverno;

class StoreAcaoRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('orgaos_governo', 'id')
            ],

            // Categorias
            'categoria_investimento_id' => [
                'required',
                'integer',
                Rule::exists('categorias_investimento', 'id')
            ],
            'tipo_acao_id' => [
                'required',
                'integer',
                Rule::exists('tipos_acao', 'id')
            ],

            // Detalhes da Ação
            'titulo' => ['required', 'string', 'max:255'],
            'numero_sei' => ['required', 'string', 'max:50'],
            'id_municipio' => [
                'required',
                'integer',
                Rule::exists('municipios', 'id_municipio')
            ],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'ano' => ['required', 'integer', 'digits:4', 'min:1900', 'max:2050'],
            'status_id' => [
                'required',
                'integer',
                Rule::exists('status_acao', 'id')
            ],
            'lideranca_solicitante_id' => [
                'nullable',
                'integer',
                Rule::exists('liderancas', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                          ->where('id_municipio', $this->id_municipio);
                })
            ],
            'observacao' => ['nullable', 'string', 'max:1000'],

            // Para update de status
            'observacao_mudanca' => ['nullable', 'string', 'max:500']
        ];
    }

    public function messages(): array
    {
        return [
            'orgao_governo_id.required' => 'Selecione um órgão do governo.',
            'orgao_governo_id.exists' => 'Órgão selecionado não existe.',
            'categoria_investimento_id.required' => 'Selecione uma categoria de investimento.',
            'tipo_acao_id.required' => 'Selecione o tipo da ação.',
            'titulo.required' => 'O título da ação é obrigatório.',
            'id_municipio.required' => 'Selecione um município.',
            'id_municipio.exists' => 'Município não encontrado.',
            'ano.required' => 'Informe o ano da ação.',
            'ano.digits' => 'O ano deve ter 4 dígitos.',
            'status_id.required' => 'Selecione o status da ação.',
            'lideranca_solicitante_id.exists' => 'Liderança não encontrada ou não pertence a este município.',
        ];
    }

    /**
     * Validação adicional após regras básicas
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Valida se liderança pertence ao município selecionado
            if ($this->lideranca_solicitante_id && $this->id_municipio) {
                $lideranca = \App\Models\Lideranca::find($this->lideranca_solicitante_id);
                
                if ($lideranca && $lideranca->id_municipio != $this->id_municipio) {
                    $validator->errors()->add(
                        'lideranca_solicitante_id',
                        'A liderança selecionada não pertence ao município escolhido.'
                    );
                }
            }
        });
    }
}