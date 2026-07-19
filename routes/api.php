<?php

use App\Http\Controllers\AcaoController;
use App\Http\Controllers\AniversarioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampanhaMaterialController;
use App\Http\Controllers\CampanhaMaterialTipoController;
use App\Http\Controllers\CampanhaPoliticoController;
use App\Http\Controllers\EstrategiaController;
use App\Http\Controllers\EstrategiaLiderancaController;
use App\Http\Controllers\EstrategiaMunicipioController;
use App\Http\Controllers\ExportAcaoController;
use App\Http\Controllers\ImportAcaoController;
use App\Http\Controllers\LiderancaController;
use App\Http\Controllers\NotificacaoClienteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AtividadeController;
use App\Http\Controllers\SuperAdmin\CargoController;
use App\Http\Controllers\SuperAdmin\CategoriaInvestimentoController;
use App\Http\Controllers\SuperAdmin\ClassificacaoController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\DeputadoController;
use App\Http\Controllers\SuperAdmin\EsferaGovernoController;
use App\Http\Controllers\SuperAdmin\MunicipioController;
use App\Http\Controllers\SuperAdmin\NotificacaoController;
use App\Http\Controllers\SuperAdmin\OrgaoGovernoController;
use App\Http\Controllers\SuperAdmin\StatusAcaoController;
use App\Http\Controllers\SuperAdmin\StatusController;
use App\Http\Controllers\SuperAdmin\TipoAcaoController;
use App\Http\Controllers\SuperAdmin\TipoOrgaoController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum', 'active')->group(function () {
    // Rotas de perfil amplo
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::put('/profile', [ProfileController::class, 'updateProfile'])->middleware('registrar.atividade');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('registrar.atividade');

    Route::get('/municipios', [MunicipioController::class, 'index']);

    Route::get('/classificacoes', [ClassificacaoController::class, 'index']);
    Route::get('/classificacoes/{classificacao}/cargos', [CargoController::class, 'porClassificacao']);

    Route::get('/esferas-governo', [EsferaGovernoController::class, 'index']);
    Route::get('/tipos-orgao', [TipoOrgaoController::class, 'index']);

    Route::get('/orgaos-governo', [OrgaoGovernoController::class, 'index']);
    Route::get('/orgaos-governo/{id}', [OrgaoGovernoController::class, 'show']);

    Route::get('/categorias-investimento', [CategoriaInvestimentoController::class, 'index']);
    Route::get('/tipos-acao', [TipoAcaoController::class, 'index']);
    Route::get('/status-acao', [StatusAcaoController::class, 'index']);
    Route::get('/cargos', [CargoController::class, 'index']);

    Route::middleware(['client', 'registrar.atividade'])->group(function () {
        Route::get('/me/dashboard', [AuthController::class, 'dashboard']);
        Route::get('/me/metadata', [AuthController::class, 'metadata']);
        Route::get('/me/aniversarios', [AniversarioController::class, 'index']);

        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::post('/', [UserManagementController::class, 'store']);
            Route::put('/{id}', [UserManagementController::class, 'update']);
            Route::patch('/{id}/toggle-status', [UserManagementController::class, 'toggleStatus']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });

        Route::prefix('/me/estrategia')->group(function () {

            Route::prefix('/lideranca')->group(function () {
                Route::get('/{id}', [EstrategiaLiderancaController::class, 'index']);
                Route::get('/{id}/selecionados', [EstrategiaLiderancaController::class, 'selecionados']);
                Route::post('/', [EstrategiaLiderancaController::class, 'store']);
            });

            Route::prefix('/municipios')->group(function () {

                Route::get('/', [EstrategiaController::class, 'index']);
                Route::get('/portifolio', [EstrategiaController::class, 'portifolio']);
                Route::get('/selecionados', [EstrategiaController::class, 'selecionados']);
                Route::get('/auto', [EstrategiaController::class, 'autoSelect']);
                Route::post('/', [EstrategiaController::class, 'update']);

                Route::prefix('/{municipio}')->group(function () {
                    Route::get('/', [EstrategiaMunicipioController::class, 'show']);
                    Route::get('/overview', [EstrategiaMunicipioController::class, 'overview']);
                    Route::get('/eleitoral', [EstrategiaMunicipioController::class, 'eleitoral']);
                    Route::get('/estrutura', [EstrategiaMunicipioController::class, 'estrutura']);
                    Route::get('/acoes', [EstrategiaMunicipioController::class, 'acoes']);
                    Route::get('/historico', [EstrategiaMunicipioController::class, 'historico']);
                });
            });
        });

        Route::prefix('/acoes')->group(function () {
            // Upload de instrumento
            Route::post('/{id}/upload-instrumento', [AcaoController::class, 'uploadInstrumento']);
            Route::delete('/{id}/instrumento', [AcaoController::class, 'deleteInstrumento']);
            Route::get('/{id}/instrumento', [AcaoController::class, 'downloadInstrumento']);

            // upload de ações
            Route::post('/import', [ImportAcaoController::class, 'store']);
            Route::get('/import/{id}', [ImportAcaoController::class, 'show']);
            Route::get('/imports', [ImportAcaoController::class, 'index']);

            Route::post('/exportar-pdf', [ExportAcaoController::class, 'store']);
            Route::get('/exports/{id}', [ExportAcaoController::class, 'show']);
            Route::get('/exports/{id}/download', [ExportAcaoController::class, 'download']);
            Route::get('/exports', [ExportAcaoController::class, 'index']);
        });

        Route::prefix('notificacoes')->group(function () {
            Route::get('/', [NotificacaoClienteController::class, 'index']);
            Route::post('/mark-all-read', [NotificacaoClienteController::class, 'markAllAsRead']);
            Route::get('/unread-count', [NotificacaoClienteController::class, 'unreadCount']);
        });

        Route::apiResource('acoes', AcaoController::class);
        Route::apiResource('liderancas', LiderancaController::class);

        // Módulo temporário de campanha eleitoral — só registrado com a
        // feature flag ligada (CAMPANHA_ATIVA no .env).
        if (config('campanha.ativo')) {
            Route::prefix('campanha')->group(function () {
                Route::apiResource('politicos', CampanhaPoliticoController::class);
                Route::apiResource('tipos-material', CampanhaMaterialTipoController::class);
                Route::apiResource('materiais', CampanhaMaterialController::class);
            });
        }
    });

    Route::prefix('super-admin')->middleware('super_admin')->group(function () {
        // Route::middleware('super_admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Status dos serviços
        Route::get('/status', [StatusController::class, 'index']);

        // Categorias
        Route::prefix('categorias')->group(function () {
            Route::post('/', [CategoriaInvestimentoController::class, 'store']);
            Route::put('/{id}', [CategoriaInvestimentoController::class, 'update']);
            Route::delete('/{id}', [CategoriaInvestimentoController::class, 'destroy']);
        });

        // Órgãos
        Route::prefix('orgaos-governo')->group(function () {
            Route::post('/', [OrgaoGovernoController::class, 'store']);
            Route::put('/{id}', [OrgaoGovernoController::class, 'update']);
            Route::delete('/{id}', [OrgaoGovernoController::class, 'destroy']);
        });

        // Deputados
        Route::prefix('deputados')->group(function () {
            Route::get('/search/name', [DeputadoController::class, 'searchByName']);
            Route::get('/search/titulo', [DeputadoController::class, 'searchByTitulo']);
            Route::get('/', [DeputadoController::class, 'index']);
            Route::post('/', [DeputadoController::class, 'store']);
            Route::get('/{id}', [DeputadoController::class, 'show']);
            Route::put('/{id}', [DeputadoController::class, 'update']);
            Route::patch('/{id}/toggle-status', [DeputadoController::class, 'toggleStatus']);
            Route::post('/{id}/create-admin', [DeputadoController::class, 'createAdminUser']);

            // Log de Atividades (inteligência de uso do gabinete)
            Route::get('/{id}/atividades', [AtividadeController::class, 'index']);
            Route::get('/{id}/atividades/timeline', [AtividadeController::class, 'timeline']);
        });

        // Notificações
        Route::prefix('notificacoes')->group(function () {
            Route::get('/', [NotificacaoController::class, 'index']);
            Route::post('/', [NotificacaoController::class, 'store']);
            Route::get('/{id}', [NotificacaoController::class, 'show']);
            Route::delete('/{id}', [NotificacaoController::class, 'destroy']);
        });
    });
});
