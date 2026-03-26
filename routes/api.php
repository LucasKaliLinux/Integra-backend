<?php

use App\Http\Controllers\AcaoController;
use App\Http\Controllers\AreaAtuacaoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\CategoriaInvestimentoController;
use App\Http\Controllers\ClassificacaoController;
use App\Http\Controllers\EsferaGovernoController;
use App\Http\Controllers\EstrategiaController;
use App\Http\Controllers\EstrategiaLiderancaController;
use App\Http\Controllers\EstrategiaMunicipioController;
use App\Http\Controllers\ExcelAcaoController;
use App\Http\Controllers\ExportAcaoController;
use App\Http\Controllers\ImportAcaoController;
use App\Http\Controllers\LiderancaController;
use App\Http\Controllers\MunicipioController;
use App\Http\Controllers\OrgaoGovernoController;
use App\Http\Controllers\StatusAcaoController;
use App\Http\Controllers\TipoAcaoController;
use App\Http\Controllers\TipoOrgaoController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Route::get('/users', [UserController::class, 'index']);
// Route::post('/users', [UserController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
// Route::get("/senha", [AuthController::class, 'senha']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/dashboard', [AuthController::class, 'dashboard']);
    Route::get('/me/metadata', [AuthController::class, 'metadata']);
    Route::post('/logout', [AuthController::class, 'logout']);

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

    Route::apiResource('acoes', AcaoController::class);
    Route::apiResource('liderancas', LiderancaController::class);

    Route::prefix('/acoes')->group(function() {

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

    Route::prefix('/me/estrategia')->group(function() {

        Route::prefix('/lideranca')->group(function() {
            Route::get('/{id}', [EstrategiaLiderancaController::class, 'index']);
            Route::get('/{id}/selecionados', [EstrategiaLiderancaController::class, 'selecionados']);
            Route::post('/', [EstrategiaLiderancaController::class, 'store']);
        });

        Route::prefix('/municipios')->group(function() {

            Route::get('/', [EstrategiaController::class, 'index']);
            Route::get('/portifolio', [EstrategiaController::class, 'portifolio']);
            Route::get('/selecionados', [EstrategiaController::class, 'selecionados']);
            Route::get('/auto', [EstrategiaController::class, 'autoSelect']);
            Route::post('/', [EstrategiaController::class, 'update']);

            Route::prefix('/{municipio}')->group(function() {
                Route::get('/', [EstrategiaMunicipioController::class, 'show']);
                Route::get('/overview', [EstrategiaMunicipioController::class, 'overview']);
                Route::get('/eleitoral', [EstrategiaMunicipioController::class, 'eleitoral']);
                Route::get('/estrutura', [EstrategiaMunicipioController::class, 'estrutura']);
                Route::get('/acoes', [EstrategiaMunicipioController::class, 'acoes']);
                Route::get('/historico', [EstrategiaMunicipioController::class, 'historico']);
            });
        });
    });
});