<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Módulo de Campanha (temporário)
    |--------------------------------------------------------------------------
    |
    | Feature flag do módulo de campanha eleitoral (políticos para
    | "casadinhas" e materiais de campanha). Quando desligada, as rotas
    | /api/campanha/* nem são registradas (404). Controle via variável de
    | ambiente CAMPANHA_ATIVA no .env — desligada por padrão.
    |
    */

    'ativo' => env('CAMPANHA_ATIVA', false),

];
