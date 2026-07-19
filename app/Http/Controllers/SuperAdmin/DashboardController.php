<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CategoriaInvestimento;
use App\Models\Deputado;
use App\Models\Notificacao;
use App\Models\OrgaoGoverno;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Visão geral do sistema
     */
    public function index()
    {
        // Cache por 5 minutos (dados mudam pouco)
        $stats = Cache::remember('super_admin_dashboard', 300, function () {
            return [
                'deputados_ativos' => Deputado::where('ativo', true)->count(),
                'total_usuarios' => User::whereNotNull('deputado_id')->count(), // Exclui super_admins
                'total_categorias' => CategoriaInvestimento::count(),
                'total_orgaos' => OrgaoGoverno::count(),
                'total_notificacoes' => Notificacao::count(),
                'notificacoes_ativas' => Notificacao::where('enviada', true)
                    ->where('data_envio', '<=', now())
                    ->count(),
            ];
        });

        return response()->json($stats);
    }
}
