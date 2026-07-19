<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Deputado;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificacaoControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function autenticarSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $superAdmin = User::factory()->create(['ativo' => true]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin, 'sanctum');

        return $superAdmin;
    }

    public function test_show_retorna_deputado_de_quem_leu_e_de_quem_nao_leu(): void
    {
        $this->autenticarSuperAdmin();

        $deputadoA = Deputado::create([
            'titulo_eleitoral' => fake()->unique()->numerify('##########'),
            'nome' => 'Deputado A '.uniqid(),
            'partido' => 'AAA',
            'cargo' => 'deputado estadual',
            'ativo' => true,
        ]);

        $deputadoB = Deputado::create([
            'titulo_eleitoral' => fake()->unique()->numerify('##########'),
            'nome' => 'Deputado B '.uniqid(),
            'partido' => 'BBB',
            'cargo' => 'deputado estadual',
            'ativo' => true,
        ]);

        $userQueLeu = User::factory()->create([
            'deputado_id' => $deputadoA->id,
            'ativo' => true,
        ]);

        $userQueNaoLeu = User::factory()->create([
            'deputado_id' => $deputadoB->id,
            'ativo' => true,
        ]);

        $notificacao = Notificacao::create([
            'titulo' => 'Aviso geral',
            'mensagem' => 'Mensagem de teste',
            'data_envio' => now(),
            'audiencia' => 'todos',
            'enviada' => true,
        ]);

        $notificacao->leituras()->attach($userQueLeu->id, ['lida_em' => now()]);

        $response = $this->getJson("/api/super-admin/notificacoes/{$notificacao->id}");

        $response->assertOk();

        $response->assertJsonFragment([
            'id' => $userQueLeu->id,
            'deputado' => [
                'id' => $deputadoA->id,
                'nome' => $deputadoA->nome,
            ],
        ]);

        $response->assertJsonFragment([
            'id' => $userQueNaoLeu->id,
            'deputado' => [
                'id' => $deputadoB->id,
                'nome' => $deputadoB->nome,
            ],
        ]);

        $visualizou = collect($response->json('visualizaram'))->firstWhere('id', $userQueLeu->id);
        $naoVisualizou = collect($response->json('nao_visualizaram'))->firstWhere('id', $userQueNaoLeu->id);

        $this->assertSame($deputadoA->id, $visualizou['deputado']['id']);
        $this->assertSame($deputadoA->nome, $visualizou['deputado']['nome']);

        $this->assertSame($deputadoB->id, $naoVisualizou['deputado']['id']);
        $this->assertSame($deputadoB->nome, $naoVisualizou['deputado']['nome']);
    }

    public function test_show_nega_acesso_para_usuario_sem_role_super_admin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $deputado = Deputado::create([
            'titulo_eleitoral' => fake()->unique()->numerify('##########'),
            'nome' => 'Deputado C '.uniqid(),
            'partido' => 'CCC',
            'cargo' => 'deputado estadual',
            'ativo' => true,
        ]);

        $usuarioComum = User::factory()->create([
            'deputado_id' => $deputado->id,
            'ativo' => true,
        ]);
        $usuarioComum->assignRole('admin');

        $this->actingAs($usuarioComum, 'sanctum');

        $notificacao = Notificacao::create([
            'titulo' => 'Aviso geral',
            'mensagem' => 'Mensagem de teste',
            'data_envio' => now(),
            'audiencia' => 'todos',
            'enviada' => true,
        ]);

        $response = $this->getJson("/api/super-admin/notificacoes/{$notificacao->id}");

        $response->assertStatus(403);
    }
}
