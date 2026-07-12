<?php

/*
|--------------------------------------------------------------------------
| Log de Atividades — mapa declarativo de captura
|--------------------------------------------------------------------------
|
| Inteligência de USO por gabinete (NÃO auditoria de segurança). Só o grupo
| de middleware `client` é capturado (usuários do gabinete). Ações de
| super_admin ficam de fora.
|
| Cada entrada mapeia `ClasseController@metodo` para:
|   - categoria   : domínio do evento (enum fechado)
|   - acao        : verbo do evento (enum fechado)
|   - recurso_tipo: tipo do recurso afetado (ou null)
|   - camada      : 'A' = ação de negócio 1:1 (loga sempre em 2xx)
|                   'B' = navegação/GET (throttle: 1 evento por
|                         (user, categoria) a cada 10 min)
|
| A chave é `class_basename(controller).'@'.metodo`.
|
*/

return [

    /*
    | Nunca loga (denylist explícita). Evita distorcer "módulos mais usados"
    | com polling e GETs de apoio a formulários.
    */
    'denylist' => [
        // Polling do sino de notificações.
        'NotificacaoClienteController@unreadCount',
        // GETs de apoio a formulários (lookups) — ficam fora do grupo client,
        // mas listados aqui por segurança/documentação.
        'OrgaoGovernoController@index',
        'OrgaoGovernoController@show',
        'CategoriaInvestimentoController@index',
        'TipoAcaoController@index',
    ],

    'mapa' => [

        // ---- Usuários do gabinete (UserManagementController) ----
        'UserManagementController@store' => ['categoria' => 'usuario', 'acao' => 'criar', 'recurso_tipo' => 'usuario', 'camada' => 'A'],
        'UserManagementController@update' => ['categoria' => 'usuario', 'acao' => 'editar', 'recurso_tipo' => 'usuario', 'camada' => 'A'],
        'UserManagementController@toggleStatus' => ['categoria' => 'usuario', 'acao' => 'desativar_ativar', 'recurso_tipo' => 'usuario', 'camada' => 'A'],
        'UserManagementController@destroy' => ['categoria' => 'usuario', 'acao' => 'excluir', 'recurso_tipo' => 'usuario', 'camada' => 'A'],

        // ---- Ações / investimentos (AcaoController) ----
        'AcaoController@store' => ['categoria' => 'acao', 'acao' => 'criar', 'recurso_tipo' => 'acao', 'camada' => 'A'],
        'AcaoController@update' => ['categoria' => 'acao', 'acao' => 'editar', 'recurso_tipo' => 'acao', 'camada' => 'A'],
        'AcaoController@destroy' => ['categoria' => 'acao', 'acao' => 'excluir', 'recurso_tipo' => 'acao', 'camada' => 'A'],
        'AcaoController@index' => ['categoria' => 'acao', 'acao' => 'visualizar', 'recurso_tipo' => 'acao', 'camada' => 'B'],
        'AcaoController@show' => ['categoria' => 'acao', 'acao' => 'visualizar', 'recurso_tipo' => 'acao', 'camada' => 'B'],

        // ---- Instrumentos das ações ----
        'AcaoController@uploadInstrumento' => ['categoria' => 'acao_instrumento', 'acao' => 'enviar', 'recurso_tipo' => 'acao', 'camada' => 'A'],
        'AcaoController@downloadInstrumento' => ['categoria' => 'acao_instrumento', 'acao' => 'baixar', 'recurso_tipo' => 'acao', 'camada' => 'A'],
        'AcaoController@deleteInstrumento' => ['categoria' => 'acao_instrumento', 'acao' => 'excluir', 'recurso_tipo' => 'acao', 'camada' => 'A'],

        // ---- Importação de ações ----
        'ImportAcaoController@store' => ['categoria' => 'acao_importacao', 'acao' => 'iniciar', 'recurso_tipo' => 'import', 'camada' => 'A'],
        'ImportAcaoController@show' => ['categoria' => 'acao_importacao', 'acao' => 'consultar', 'recurso_tipo' => 'import', 'camada' => 'B'],
        'ImportAcaoController@index' => ['categoria' => 'acao_importacao', 'acao' => 'consultar', 'recurso_tipo' => 'import', 'camada' => 'B'],

        // ---- Exportação de ações ----
        'ExportAcaoController@store' => ['categoria' => 'acao_exportacao', 'acao' => 'iniciar', 'recurso_tipo' => 'export', 'camada' => 'A'],
        'ExportAcaoController@download' => ['categoria' => 'acao_exportacao', 'acao' => 'baixar', 'recurso_tipo' => 'export', 'camada' => 'A'],

        // ---- Lideranças (LiderancaController) ----
        'LiderancaController@store' => ['categoria' => 'lideranca', 'acao' => 'criar', 'recurso_tipo' => 'lideranca', 'camada' => 'A'],
        'LiderancaController@update' => ['categoria' => 'lideranca', 'acao' => 'editar', 'recurso_tipo' => 'lideranca', 'camada' => 'A'],
        'LiderancaController@destroy' => ['categoria' => 'lideranca', 'acao' => 'excluir', 'recurso_tipo' => 'lideranca', 'camada' => 'A'],
        'LiderancaController@index' => ['categoria' => 'lideranca', 'acao' => 'visualizar', 'recurso_tipo' => 'lideranca', 'camada' => 'B'],
        'LiderancaController@show' => ['categoria' => 'lideranca', 'acao' => 'visualizar', 'recurso_tipo' => 'lideranca', 'camada' => 'B'],

        // ---- Estratégia: municípios ----
        'EstrategiaController@index' => ['categoria' => 'estrategia', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],
        'EstrategiaController@portifolio' => ['categoria' => 'estrategia', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],
        'EstrategiaController@selecionados' => ['categoria' => 'estrategia', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],
        'EstrategiaController@autoSelect' => ['categoria' => 'estrategia', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],
        'EstrategiaController@update' => ['categoria' => 'estrategia', 'acao' => 'selecionar_municipios', 'recurso_tipo' => null, 'camada' => 'A'],

        // ---- Estratégia: lideranças ----
        'EstrategiaLiderancaController@index' => ['categoria' => 'estrategia', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],
        'EstrategiaLiderancaController@selecionados' => ['categoria' => 'estrategia', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],
        'EstrategiaLiderancaController@store' => ['categoria' => 'estrategia', 'acao' => 'selecionar_lideranca', 'recurso_tipo' => null, 'camada' => 'A'],

        // ---- Estratégia: detalhe de município ----
        'EstrategiaMunicipioController@show' => ['categoria' => 'municipio', 'acao' => 'visualizar', 'recurso_tipo' => 'municipio', 'camada' => 'B'],
        'EstrategiaMunicipioController@overview' => ['categoria' => 'municipio', 'acao' => 'visualizar', 'recurso_tipo' => 'municipio', 'camada' => 'B'],
        'EstrategiaMunicipioController@eleitoral' => ['categoria' => 'municipio', 'acao' => 'visualizar', 'recurso_tipo' => 'municipio', 'camada' => 'B'],
        'EstrategiaMunicipioController@estrutura' => ['categoria' => 'municipio', 'acao' => 'visualizar', 'recurso_tipo' => 'municipio', 'camada' => 'B'],
        'EstrategiaMunicipioController@acoes' => ['categoria' => 'municipio', 'acao' => 'visualizar', 'recurso_tipo' => 'municipio', 'camada' => 'B'],
        'EstrategiaMunicipioController@historico' => ['categoria' => 'municipio', 'acao' => 'visualizar', 'recurso_tipo' => 'municipio', 'camada' => 'B'],

        // ---- Aniversários ----
        'AniversarioController@index' => ['categoria' => 'aniversario', 'acao' => 'visualizar', 'recurso_tipo' => null, 'camada' => 'B'],

        // ---- Notificações do gabinete ----
        'NotificacaoClienteController@index' => ['categoria' => 'notificacao', 'acao' => 'visualizar_lista', 'recurso_tipo' => null, 'camada' => 'B'],
        'NotificacaoClienteController@markAllAsRead' => ['categoria' => 'notificacao', 'acao' => 'marcar_lidas', 'recurso_tipo' => null, 'camada' => 'A'],

        // ---- Perfil do próprio usuário ----
        'ProfileController@updateProfile' => ['categoria' => 'perfil', 'acao' => 'editar_dados', 'recurso_tipo' => null, 'camada' => 'A'],
        'ProfileController@updatePassword' => ['categoria' => 'perfil', 'acao' => 'alterar_senha', 'recurso_tipo' => null, 'camada' => 'A'],

        // ---- Campanha (só quando config('campanha.ativo') estiver ligada) ----
        'CampanhaPoliticoController@store' => ['categoria' => 'campanha_politico', 'acao' => 'criar', 'recurso_tipo' => 'campanha_politico', 'camada' => 'A'],
        'CampanhaPoliticoController@update' => ['categoria' => 'campanha_politico', 'acao' => 'editar', 'recurso_tipo' => 'campanha_politico', 'camada' => 'A'],
        'CampanhaPoliticoController@destroy' => ['categoria' => 'campanha_politico', 'acao' => 'excluir', 'recurso_tipo' => 'campanha_politico', 'camada' => 'A'],
        'CampanhaPoliticoController@index' => ['categoria' => 'campanha_politico', 'acao' => 'visualizar', 'recurso_tipo' => 'campanha_politico', 'camada' => 'B'],
        'CampanhaPoliticoController@show' => ['categoria' => 'campanha_politico', 'acao' => 'visualizar', 'recurso_tipo' => 'campanha_politico', 'camada' => 'B'],

        'CampanhaMaterialController@store' => ['categoria' => 'campanha_material', 'acao' => 'criar', 'recurso_tipo' => 'campanha_material', 'camada' => 'A'],
        'CampanhaMaterialController@update' => ['categoria' => 'campanha_material', 'acao' => 'editar', 'recurso_tipo' => 'campanha_material', 'camada' => 'A'],
        'CampanhaMaterialController@destroy' => ['categoria' => 'campanha_material', 'acao' => 'excluir', 'recurso_tipo' => 'campanha_material', 'camada' => 'A'],
        'CampanhaMaterialController@index' => ['categoria' => 'campanha_material', 'acao' => 'visualizar', 'recurso_tipo' => 'campanha_material', 'camada' => 'B'],
        'CampanhaMaterialController@show' => ['categoria' => 'campanha_material', 'acao' => 'visualizar', 'recurso_tipo' => 'campanha_material', 'camada' => 'B'],
    ],
];
