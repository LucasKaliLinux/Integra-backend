<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiderancaResource extends JsonResource
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
            'nome' => $this->nome,
            'telefone' => $this->telefone,
            'instagram' => $this->instagram,
            'data_nascimento' => $this->data_nascimento,
            'alinhamento' => $this->alinhamento,
            'observacao' => $this->observacao,

            'municipio' => $this->whenLoaded('municipio', function () {
                return [
                    'id_municipio' => $this->municipio->id_municipio,
                    'nome' => $this->municipio->nome,
                ];
            }),

            'classificacao' => $this->whenLoaded('classificacao', function () {
                return [
                    'id' => $this->classificacao->id,
                    'nome' => $this->classificacao->nome,
                    'slug' => $this->classificacao->slug,
                ];
            }),

            'funcao' => $this->whenLoaded('funcao', function () {
                return [
                    'id' => $this->funcao->id,
                    'nome' => $this->funcao->nome,
                    'slug' => $this->funcao->slug,
                ];
            }),
        ];
    }
}
