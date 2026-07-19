<?php

namespace App\Http\Requests;

/**
 * Mesmas regras do Store: o painel envia o objeto completo no update.
 * As transições de status são livres (sem máquina de estados).
 */
class UpdateCampanhaMaterialRequest extends StoreCampanhaMaterialRequest {}
