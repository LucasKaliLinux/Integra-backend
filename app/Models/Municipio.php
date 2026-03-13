<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'municipios';
    protected $primaryKey = 'id_municipio';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nome',
        'populacao'
    ];

    public function scopeFilter($query, $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $v) {
                // Detecta qual alias está sendo usado
                $table = $q->getQuery()->from;
                
                // Se for "municipios as m", usa "m.nome"
                // Se for "municipios", usa "municipios.nome"
                $column = str_contains($table, ' as m') ? 'm.nome' : 'municipios.nome';
                
                return $q->where($column, 'like', "{$v}%");
            });
    }
}
