<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaInvestimento extends Model
{
    protected $table = 'categorias_investimento';

    protected $fillable = ['nome', 'slug'];

    public function acoes()
    {
        return $this->hasMany(Acao::class, 'categoria_investimento_id');
    }
}