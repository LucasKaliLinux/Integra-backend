<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassificacaoLideranca extends Model
{
    protected $table = 'classificacoes_lideranca';

    protected $fillable = ['nome', 'slug'];

    public function cargos()
    {
        return $this->hasMany(CargoLideranca::class, 'classificacao_id');
    }
}
