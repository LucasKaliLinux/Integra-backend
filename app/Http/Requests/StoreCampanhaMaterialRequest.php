<?php

namespace App\Http\Requests;

use App\Models\CampanhaMaterial;
use App\Models\CampanhaMaterialTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampanhaMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deputadoId = $this->user()->deputado_id;

        // Ao TROCAR de tipo, o novo tipo precisa estar ativo. Mas manter o
        // tipo atual (ainda que ele tenha sido desativado depois) é permitido
        // — senão o material ficaria "preso" sem poder ser editado. A rota do
        // apiResource "materiais" usa o parâmetro {materiai} (singularização
        // do Laravel); como o controller não usa route model binding, o
        // valor chega como string/id, não como model.
        $materialId = $this->route('materiai');
        $materialAtual = $materialId
            ? CampanhaMaterial::where('deputado_id', $deputadoId)->find($materialId)
            : null;
        $tipoAtualId = $materialAtual?->tipo_material_id;

        // Manter o mesmo tipo é sempre permitido (mesma leniência aplicada
        // à checagem de "ativo" e à validação da variante logo abaixo).
        $mantendoTipo = (int) $this->input('tipo_material_id') === (int) $tipoAtualId;
        $exigeAtivo = ! $mantendoTipo;

        // Variantes do tipo escolhido (snapshot: o material grava a string,
        // sem FK). Se o tipo não existe/é de outro deputado, a regra de
        // "exists" abaixo já reprova o tipo_material_id.
        $tipoEscolhido = CampanhaMaterialTipo::where('deputado_id', $deputadoId)
            ->find($this->input('tipo_material_id'));
        $variantesDoTipo = $tipoEscolhido?->variantes ?? [];

        $rules = [
            'id_municipio' => 'required|integer|exists:municipios,id_municipio',

            // Liderança precisa pertencer ao deputado do usuário autenticado.
            'lideranca_id' => [
                'nullable',
                'integer',
                Rule::exists('liderancas', 'id')->where('deputado_id', $deputadoId),
            ],

            'tipo_material_id' => [
                'required',
                'integer',
                Rule::exists('campanha_material_tipos', 'id')
                    ->where('deputado_id', $deputadoId)
                    ->when($exigeAtivo, fn ($query) => $query->where('ativo', true)),
            ],
            'quantidade' => 'required|integer|min:1',
            'status' => ['nullable', Rule::in(CampanhaMaterial::STATUS)],

            // Data de ENTREGA — pode ser futura.
            'data' => 'required|date',

            // Sempre opcional, mesmo com status "entregue".
            'recebedor' => 'nullable|string|max:255',

            'arte_url' => 'nullable|url:http,https|max:255',
            'observacao' => 'nullable|string|max:5000',
        ];

        // Variante: obrigatória e restrita ao catálogo quando o tipo tem
        // variantes; proibida quando o tipo não tem. Ao EDITAR mantendo o
        // mesmo tipo, o snapshot já gravado é sempre aceito — mesmo que o
        // catálogo do tipo tenha mudado depois (não quebra a edição).
        if (! empty($variantesDoTipo)) {
            $valoresPermitidos = $variantesDoTipo;

            if ($mantendoTipo && $materialAtual?->variante !== null) {
                $valoresPermitidos[] = $materialAtual->variante;
            }

            $rules['variante'] = [
                'required',
                'string',
                Rule::in(array_values(array_unique($valoresPermitidos))),
            ];
        } else {
            $rules['variante'] = ['prohibited'];
        }

        // Slots de composição: cada político precisa (a) pertencer ao mesmo
        // deputado do usuário autenticado e (b) ter o cargo compatível com o slot.
        foreach (CampanhaMaterial::SLOTS_COMPOSICAO as $campo => $cargo) {
            $rules[$campo] = [
                'nullable',
                'integer',
                Rule::exists('campanha_politicos', 'id')
                    ->where('deputado_id', $deputadoId)
                    ->where('cargo', $cargo),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'id_municipio.required' => 'O município é obrigatório.',
            'id_municipio.exists' => 'Município inválido.',

            'lideranca_id.exists' => 'Liderança inválida ou não pertence ao seu deputado.',

            'tipo_material_id.required' => 'O tipo de material é obrigatório.',
            'tipo_material_id.exists' => 'Tipo de material inválido, inativo ou não pertence ao seu deputado.',

            'variante.required' => 'A variante é obrigatória para este tipo de material.',
            'variante.string' => 'A variante deve ser um texto.',
            'variante.in' => 'Variante inválida para o tipo de material selecionado.',
            'variante.prohibited' => 'O tipo de material selecionado não possui variantes.',

            'quantidade.required' => 'A quantidade é obrigatória.',
            'quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'quantidade.min' => 'A quantidade deve ser de pelo menos 1.',

            'status.in' => 'Status inválido. Escolha entre: solicitado, produzido, entregue ou cancelado.',

            'data.required' => 'A data de entrega é obrigatória.',
            'data.date' => 'Data de entrega inválida.',

            'recebedor.max' => 'O recebedor não pode ter mais de 255 caracteres.',
            'arte_url.url' => 'A URL da arte é inválida. Use um endereço http ou https.',
            'observacao.max' => 'A observação não pode ter mais de 5000 caracteres.',
        ];

        foreach (CampanhaMaterial::SLOTS_COMPOSICAO as $campo => $cargo) {
            $messages["{$campo}.exists"] = "Político inválido para o slot de {$cargo}: ele precisa estar cadastrado para o seu deputado e ter o cargo \"{$cargo}\".";
            $messages["{$campo}.integer"] = "Identificador inválido para o político do slot de {$cargo}.";
        }

        return $messages;
    }
}
