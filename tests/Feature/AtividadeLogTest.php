<?php

namespace Tests\Feature;

use App\Models\AtividadeLog;
use App\Models\AtividadeSessao;
use App\Models\Deputado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AtividadeLogTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Zera o throttle de camada B e o rate limit de login (usam cache).
        Cache::flush();
    }

    private function criarDeputado(string $nome = 'Deputado Atividade'): Deputado
    {
        return Deputado::create([
            'titulo_eleitoral' => fake()->unique()->numerify('############'),
            'nome' => $nome.' '.uniqid(),
            'partido' => 'AAA',
            'cargo' => 'deputado estadual',
            'ativo' => true,
        ]);
    }

    private function criarUsuario(Deputado $deputado, string $role = 'admin', array $attrs = []): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create(array_merge([
            'deputado_id' => $deputado->id,
            'ativo' => true,
        ], $attrs));

        $user->assignRole($role);

        return $user;
    }

    private function criarSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['deputado_id' => null, 'ativo' => true]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function seedLog(Deputado $dep, ?User $user, string $categoria, string $acao, array $extra = []): AtividadeLog
    {
        return AtividadeLog::create(array_merge([
            'deputado_id' => $dep->id,
            'user_id' => $user?->id,
            'categoria' => $categoria,
            'acao' => $acao,
            'metodo_http' => 'GET',
            'rota' => 'x',
        ], $extra));
    }

    // ---------------------------------------------------------------------
    // Middleware
    // ---------------------------------------------------------------------

    public function test_middleware_grava_evento_em_resposta_2xx(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/notificacoes')->assertOk();

        $this->assertDatabaseHas('atividade_logs', [
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'categoria' => 'notificacao',
            'acao' => 'visualizar_lista',
        ]);
    }

    public function test_middleware_nao_grava_em_resposta_nao_2xx(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // Payload inválido → 422 (UserManagementController@store = usuario.criar).
        $this->postJson('/api/users', [])->assertStatus(422);

        $this->assertDatabaseMissing('atividade_logs', [
            'deputado_id' => $deputado->id,
            'categoria' => 'usuario',
            'acao' => 'criar',
        ]);
    }

    public function test_middleware_respeita_denylist(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // unread-count é polling do sino → denylist.
        $this->getJson('/api/notificacoes/unread-count')->assertOk();

        $this->assertSame(0, AtividadeLog::where('user_id', $user->id)->count());
    }

    public function test_middleware_aplica_throttle_camada_b(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // Duas visitas seguidas ao mesmo módulo → só 1 evento (throttle 10 min).
        $this->getJson('/api/notificacoes')->assertOk();
        $this->getJson('/api/notificacoes')->assertOk();

        $this->assertSame(
            1,
            AtividadeLog::where('user_id', $user->id)
                ->where('categoria', 'notificacao')
                ->where('acao', 'visualizar_lista')
                ->count()
        );
    }

    public function test_middleware_atualiza_presenca_da_sessao_aberta(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $sessao = AtividadeSessao::create([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'iniciada_em' => now()->subHour(),
            'ultima_atividade_em' => now()->subHour(),
            'origem' => 'login',
        ]);

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/notificacoes/unread-count')->assertOk();

        $sessao->refresh();
        $this->assertTrue($sessao->ultima_atividade_em->gt(now()->subMinutes(2)));
        $this->assertNull($sessao->encerrada_em);
    }

    // ---------------------------------------------------------------------
    // Sessões e auth
    // ---------------------------------------------------------------------

    public function test_login_abre_sessao_e_grava_evento(): void
    {
        $deputado = $this->criarDeputado();
        $email = fake()->unique()->safeEmail();
        $this->criarUsuario($deputado, 'admin', [
            'email' => $email,
            'password' => Hash::make('Senha@Forte1'),
        ]);

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'Senha@Forte1',
        ])->assertOk();

        $this->assertDatabaseHas('atividade_sessoes', [
            'deputado_id' => $deputado->id,
            'encerrada_em' => null,
            'origem' => 'login',
        ]);

        $this->assertDatabaseHas('atividade_logs', [
            'deputado_id' => $deputado->id,
            'categoria' => 'auth',
            'acao' => 'login',
        ]);
    }

    public function test_logout_fecha_sessao_e_calcula_duracao(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        // Sessão aberta há 20 min (simula o login prévio).
        $sessao = AtividadeSessao::create([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'iniciada_em' => now()->subMinutes(20),
            'ultima_atividade_em' => now(),
            'origem' => 'login',
        ]);

        // Token real (sem login HTTP) para o logout via AuthController disparar
        // o evento Logout com autenticação por token.
        $token = $user->createToken('teste')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $sessao->refresh();

        $this->assertNotNull($sessao->encerrada_em);
        $this->assertNotNull($sessao->duracao_segundos);
        $this->assertGreaterThanOrEqual(20 * 60 - 5, $sessao->duracao_segundos);

        $this->assertDatabaseHas('atividade_logs', [
            'deputado_id' => $deputado->id,
            'categoria' => 'auth',
            'acao' => 'logout',
        ]);
    }

    public function test_login_falha_anonimo_nao_grava(): void
    {
        $this->postJson('/api/login', [
            'email' => 'ninguem-'.uniqid().'@exemplo.com',
            'password' => 'qualquer-coisa',
        ])->assertStatus(401);

        $this->assertSame(0, AtividadeLog::where('acao', 'login_falha')->count());
    }

    public function test_login_falha_de_usuario_existente_grava_sem_email(): void
    {
        $deputado = $this->criarDeputado();
        $email = fake()->unique()->safeEmail();
        $user = $this->criarUsuario($deputado, 'admin', [
            'email' => $email,
            'password' => Hash::make('Senha@Forte1'),
        ]);

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'senha-errada',
        ])->assertStatus(401);

        $this->assertDatabaseHas('atividade_logs', [
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'categoria' => 'auth',
            'acao' => 'login_falha',
        ]);

        // Nenhuma coluna guarda o e-mail (checagem defensiva de privacidade).
        $log = AtividadeLog::where('acao', 'login_falha')->latest('id')->first();
        $this->assertNull($log->contexto);
    }

    public function test_comando_fecha_sessoes_inativas_por_inferencia(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $sessao = AtividadeSessao::create([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'iniciada_em' => now()->subMinutes(90),
            'ultima_atividade_em' => now()->subMinutes(45),
            'origem' => 'login',
        ]);

        $this->artisan('atividades:fechar-sessoes-inativas')->assertSuccessful();

        $sessao->refresh();
        $this->assertNotNull($sessao->encerrada_em);
        $this->assertSame('inferida', $sessao->origem);
        $this->assertSame(45 * 60, $sessao->duracao_segundos);
    }

    // ---------------------------------------------------------------------
    // Model — whitelist do contexto
    // ---------------------------------------------------------------------

    public function test_contexto_dropa_chaves_nao_permitidas(): void
    {
        $log = new AtividadeLog;

        $log->contexto = [
            'acao_id' => 42,            // permitida
            'titulo' => 'texto livre',  // proibida → dropada
            'aninhado' => ['x' => 1],   // profundidade > 1 → dropada
            'status_slug' => 'aprovado', // permitida
            'longo' => str_repeat('x', 100), // acima do limite → dropada
        ];

        $this->assertSame(['acao_id' => 42, 'status_slug' => 'aprovado'], $log->contexto);
    }

    public function test_contexto_totalmente_proibido_vira_null(): void
    {
        $log = new AtividadeLog;

        $log->contexto = ['password' => 'x', 'titulo' => 'y', 'valor' => 1000.5];

        $this->assertNull($log->contexto);
    }

    // ---------------------------------------------------------------------
    // Endpoints super-admin — escopo por deputado (sem IDOR)
    // ---------------------------------------------------------------------

    public function test_timeline_escopa_por_deputado_sem_idor(): void
    {
        $depA = $this->criarDeputado('Dep A');
        $depB = $this->criarDeputado('Dep B');
        $userA = $this->criarUsuario($depA);
        $userB = $this->criarUsuario($depB);

        $this->seedLog($depA, $userA, 'acao', 'criar', ['recurso_tipo' => 'acao', 'recurso_id' => 1]);
        $logB = $this->seedLog($depB, $userB, 'lideranca', 'criar', ['recurso_tipo' => 'lideranca', 'recurso_id' => 2]);

        $this->actingAs($this->criarSuperAdmin(), 'sanctum');

        $resp = $this->getJson("/api/super-admin/deputados/{$depA->id}/atividades/timeline");
        $resp->assertOk();

        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($logB->id));
        $this->assertTrue($ids->every(fn ($id) => AtividadeLog::find($id)->deputado_id === $depA->id));
    }

    public function test_dashboard_agrega_apenas_o_deputado_da_rota(): void
    {
        $depA = $this->criarDeputado('Dash A');
        $depB = $this->criarDeputado('Dash B');
        $userA = $this->criarUsuario($depA);
        $userB = $this->criarUsuario($depB);

        $this->seedLog($depA, $userA, 'auth', 'login');
        $this->seedLog($depA, $userA, 'acao', 'criar');
        $this->seedLog($depB, $userB, 'auth', 'login');

        $this->actingAs($this->criarSuperAdmin(), 'sanctum');

        $resp = $this->getJson("/api/super-admin/deputados/{$depA->id}/atividades?periodo=30d");
        $resp->assertOk();

        $resp->assertJsonPath('kpis.total_logins', 1);
        $resp->assertJsonPath('kpis.total_eventos', 2);
        $resp->assertJsonPath('kpis.usuarios_ativos', 1);
        $resp->assertJsonCount(24, 'distribuicao_horaria');
    }

    public function test_endpoint_de_atividades_exige_super_admin(): void
    {
        $deputado = $this->criarDeputado();
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->getJson("/api/super-admin/deputados/{$deputado->id}/atividades")->assertStatus(403);
        $this->getJson("/api/super-admin/deputados/{$deputado->id}/atividades/timeline")->assertStatus(403);
    }
}
