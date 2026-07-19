<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StatusController extends Controller
{
    /**
     * Status dos serviços principais do sistema.
     * Cada serviço é verificado de fato (conectividade + latência).
     */
    public function index()
    {
        return response()->json([
            'servicos' => [
                $this->check('API Principal', fn () => true),
                $this->check('Banco de Dados', fn () => DB::select('select 1')),
                $this->check('Cache', function () {
                    Cache::put('status_healthcheck', 1, 5);

                    return Cache::get('status_healthcheck') === 1;
                }),
                $this->check('Storage', function () {
                    return Storage::disk('local')->put('status_healthcheck.txt', 'ok')
                        && Storage::disk('local')->delete('status_healthcheck.txt');
                }),
            ],
            'verificado_em' => now()->toIso8601String(),
        ]);
    }

    /**
     * Executa uma verificação medindo a latência e classificando o status.
     */
    private function check(string $servico, callable $probe): array
    {
        $inicio = microtime(true);

        try {
            $ok = $probe() !== false;
            $latencia = (int) round((microtime(true) - $inicio) * 1000);
            $status = $ok ? ($latencia > 800 ? 'degradado' : 'online') : 'offline';
        } catch (Throwable $e) {
            $latencia = (int) round((microtime(true) - $inicio) * 1000);
            $status = 'offline';
        }

        return [
            'servico' => $servico,
            'status' => $status,
            'latencia_ms' => $latencia,
            'verificado_em' => 'agora',
        ];
    }
}
