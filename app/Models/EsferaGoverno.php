<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EsferaGoverno extends Model
{
    protected $table = 'esferas_governo';

    protected $fillable = ['nome', 'slug'];

    public function tiposOrgao()
    {
        return $this->hasMany(TipoOrgao::class, 'esfera_governo_id');
    }
}