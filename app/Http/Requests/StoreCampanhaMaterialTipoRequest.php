<?php

namespace App\Http\Requests;

use App\Models\CampanhaMaterialTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampanhaMaterialTipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza as variantes antes da validação: faz trim e descarta
     * strings vazias (o cast/model já guarda como array JSON).
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('variantes'))) {
            $variantes = collect($this->input('variantes'))
                ->map(fn ($v) => is_string($v) ? trim($v) : $v)
                ->filter(fn ($v) => is_string($v) && $v !== '')
                ->values()
                ->all();

            $this->merge(['variantes' => $variantes]);
        }

        if (is_string($this->input('unidade'))) {
            $this->merge(['unidade' => trim($this->input('unidade'))]);
        }
    }

    public function rules(): array
    {
        $deputadoId = $this->user()->deputado_id;

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campanha_material_tipos', 'nome')
                    ->where('deputado_id', $deputadoId)
                    ->ignore($this->route('tipos_material')),
            ],

            'categoria' => ['required', Rule::in(CampanhaMaterialTipo::CATEGORIAS)],

            'unidade' => 'required|string|max:50',

            'variantes' => 'sometimes|nullable|array',
            'variantes.*' => 'string|max:100|distinct',

            'ativo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do tipo de material é obrigatório.',
            'nome.max' => 'O nome do tipo de material não pode ter mais de 255 caracteres.',
            'nome.unique' => 'Já existe um tipo de material com esse nome.',

            'categoria.required' => 'A categoria é obrigatória.',
            'categoria.in' => 'Categoria inválida. Escolha entre: impresso, digital, brinde, vestuario, sinalizacao ou outro.',

            'unidade.required' => 'A unidade é obrigatória.',
            'unidade.max' => 'A unidade não pode ter mais de 50 caracteres.',

            'variantes.array' => 'As variantes devem ser uma lista.',
            'variantes.*.string' => 'Cada variante deve ser um texto.',
            'variantes.*.max' => 'Cada variante não pode ter mais de 100 caracteres.',
            'variantes.*.distinct' => 'Não é permitido repetir variantes.',

            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        ];
    }
}
