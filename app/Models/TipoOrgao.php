<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoOrgao extends Model
{
    protected $table = 'tipos_orgao';

    protected $fillable = [
        'esfera_governo_id',
        'nome',
        'slug'
    ];

    public function esferaGoverno()
    {
        return $this->belongsTo(EsferaGoverno::class, 'esfera_governo_id');
    }

    public function orgaos()
    {
        return $this->hasMany(OrgaoGoverno::class, 'tipo_orgao_id');
    }
}