<?php

namespace App\Http\Resources;

use App\Models\CampanhaPolitico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampanhaMaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantidade' => $this->quantidade,
            'status' => $this->status,

            'municipio' => $this->whenLoaded('municipio', fn () => [
                'id_municipio' => $this->municipio->id_municipio,
                'nome' => $this->municipio->nome,
            ]),

            'lideranca' => $this->whenLoaded('lideranca', fn () => [
                'id' => $this->lideranca->id,
                'nome' => $this->lideranca->nome,
            ], null),

            'tipo_material' => $this->whenLoaded('tipoMaterial', fn () => [
                'id' => $this->tipoMaterial->id,
                'nome' => $this->tipoMaterial->nome,
                'categoria' => $this->tipoMaterial->categoria,
                'unidade' => $this->tipoMaterial->unidade,
            ], null),

            'variante' => $this->variante,

            'composicao' => [
                'estadual' => $this->politicoResumo($this->politicoEstadual),
                'federal' => $this->politicoResumo($this->politicoFederal),
                'senador' => $this->politicoResumo($this->politicoSenador),
                'governador' => $this->politicoResumo($this->politicoGovernador),
                'presidente' => $this->politicoResumo($this->politicoPresidente),
            ],

            'recebedor' => $this->recebedor,
            'data' => $this->data?->format('d/m/Y'),
            'arte_url' => $this->arte_url,
            'observacao' => $this->observacao,

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ], null),

            'created_at' => $this->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function politicoResumo(?CampanhaPolitico $politico): ?array
    {
        if (! $politico) {
            return null;
        }

        return [
            'id' => $politico->id,
            'nome' => $politico->nome,
            'nome_urna' => $politico->nome_urna,
            'numero_eleitoral' => $politico->numero_eleitoral,
            'partido' => $politico->partido,
            'cor_principal' => $politico->cor_principal,
        ];
    }
}
