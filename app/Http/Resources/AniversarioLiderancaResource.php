<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AniversarioLiderancaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dataNascimento = Carbon::parse($this->data_nascimento);
        $proximoAniversario = Carbon::parse($this->proximo_aniversario);

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'telefone' => $this->telefone,
            'data_nascimento' => $dataNascimento->format('Y-m-d'),
            'proxima_data_aniversario' => $proximoAniversario->format('Y-m-d'),
            'dias_ate_aniversario' => (int) $this->dias_ate_aniversario,
            'idade_completar' => $proximoAniversario->diffInYears($dataNascimento, true),
            'alinhamento' => $this->alinhamento,

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
