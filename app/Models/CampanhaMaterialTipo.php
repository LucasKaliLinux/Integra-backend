<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampanhaMaterialTipo extends Model
{
    /**
     * Categorias aceitas — enum FIXO (validado no FormRequest, sem tabela).
     */
    public const CATEGORIAS = ['impresso', 'digital', 'brinde', 'vestuario', 'sinalizacao', 'outro'];

    protected $table = 'campanha_material_tipos';

    /**
     * "deputado_id" fica FORA do fillable de propósito: é atribuído
     * explicitamente no controller a partir do usuário autenticado.
     */
    protected $fillable = [
        'nome',
        'categoria',
        'unidade',
        'variantes',
        'ativo',
    ];

    /**
     * O MySQL não aceita DEFAULT em coluna JSON; garantimos o array vazio
     * aqui para que novos tipos sem variantes tenham "[]" e não NULL.
     */
    protected $attributes = [
        'variantes' => '[]',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'variantes' => 'array',
    ];

    public function scopeForDeputado($query, $deputadoId)
    {
        return $query->where('deputado_id', $deputadoId);
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTOS
    |--------------------------------------------------------------------------
    */

    public function deputado()
    {
        return $this->belongsTo(Deputado::class);
    }

    public function materiais()
    {
        return $this->hasMany(CampanhaMaterial::class, 'tipo_material_id');
    }
}
