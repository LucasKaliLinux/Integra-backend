<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargoLideranca extends Model
{
    protected $table = 'cargos_lideranca';

    protected $fillable = [
        'classificacao_id',
        'nome',
        'slug',
    ];

    public function classificacao()
    {
        return $this->belongsTo(ClassificacaoLideranca::class, 'classificacao_id');
    }
}
