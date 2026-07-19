<?php

namespace Tests\Feature;

use App\Models\Deputado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EstrategiaLiderancaControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const ANO_ELEICAO = 2024;

    private function criarDeputado(string $nome): Deputado
    {
        return Deputado::create([
            'titulo_eleitoral' => fake()->unique()->numerify('##########'),
            'nome' => $nome.' '.uniqid(),
            'partido' => 'AAA',
            'cargo' => 'deputado estadual',
            'ativo' => true,
        ]);
    }

    private function criarUsuarioGabinete(Deputado $deputado): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Busca no banco real um candidato (prefeito ou vereador) de um município
     * com dados de votação e candidatura completos, para usar em testes de
     * ponta a ponta contra as tabelas `votacao`/`candidaturas` (dados reais
     * do TSE já carregados no banco, fora do escopo de migrations).
     */
    private function buscarCandidatoDeTeste(): object
    {
        $candidato = DB::table('votacao as v')
            ->join('candidaturas as c', 'v.titulo_eleitoral_candidato', '=', 'c.titulo_eleitoral')
            ->where('v.ano', self::ANO_ELEICAO)
            ->whereIn('v.cargo', ['prefeito', 'vereador'])
            ->select(['v.id_municipio', 'v.sequencial_candidato'])
            ->first();

        if (! $candidato) {
            $this->markTestSkipped('Nenhum dado de votação/candidatura disponível no banco para o ano '.self::ANO_ELEICAO.'.');
        }

        return $candidato;
    }

    public function test_dois_deputados_diferentes_podem_cadastrar_o_mesmo_candidato_como_lideranca(): void
    {
        $candidato = $this->buscarCandidatoDeTeste();

        $deputadoA = $this->criarDeputado('Deputado A Lideranca');
        $deputadoB = $this->criarDeputado('Deputado B Lideranca');

        $userA = $this->criarUsuarioGabinete($deputadoA);
        $userB = $this->criarUsuarioGabinete($deputadoB);

        $this->actingAs($userA, 'sanctum');
        $responseA = $this->postJson('/api/me/estrategia/lideranca/', [
            'id_municipio' => $candidato->id_municipio,
            'aliados' => [$candidato->sequencial_candidato],
            'oposicao' => [],
        ]);

        $responseA->assertCreated();

        $this->actingAs($userB, 'sanctum');
        $responseB = $this->postJson('/api/me/estrategia/lideranca/', [
            'id_municipio' => $candidato->id_municipio,
            'aliados' => [$candidato->sequencial_candidato],
            'oposicao' => [],
        ]);

        // Antes da correção, essa segunda chamada falhava com 422 por causa
        // da constraint única global (sequencial_candidato, ano), mesmo sendo
        // um deputado diferente cadastrando o candidato pela primeira vez.
        $responseB->assertCreated();

        $this->assertDatabaseHas('liderancas', [
            'deputado_id' => $deputadoA->id,
        ]);
        $this->assertDatabaseHas('liderancas', [
            'deputado_id' => $deputadoB->id,
        ]);
    }

    public function test_mesmo_deputado_nao_pode_cadastrar_o_mesmo_candidato_duas_vezes(): void
    {
        $candidato = $this->buscarCandidatoDeTeste();

        $deputado = $this->criarDeputado('Deputado Duplicidade');
        $user = $this->criarUsuarioGabinete($deputado);

        $this->actingAs($user, 'sanctum');

        $primeira = $this->postJson('/api/me/estrategia/lideranca/', [
            'id_municipio' => $candidato->id_municipio,
            'aliados' => [$candidato->sequencial_candidato],
            'oposicao' => [],
        ]);
        $primeira->assertCreated();

        $segunda = $this->postJson('/api/me/estrategia/lideranca/', [
            'id_municipio' => $candidato->id_municipio,
            'aliados' => [$candidato->sequencial_candidato],
            'oposicao' => [],
        ]);

        $segunda->assertStatus(422);
        $segunda->assertJson([
            'error' => 'Um ou mais candidatos já estão cadastrados como lideranças políticas.',
        ]);
    }

    public function test_erro_interno_nao_vaza_mensagem_crua_da_excecao(): void
    {
        $candidato = $this->buscarCandidatoDeTeste();

        $deputado = $this->criarDeputado('Deputado Erro Interno');
        $user = $this->criarUsuarioGabinete($deputado);

        $mensagemSensivel = 'detalhe interno sensível: SQLSTATE[42S02] tabela secreta';

        // store() agora adquire uma trava (Cache::lock) antes de qualquer remember;
        // com o facade mockado, precisamos devolver um lock funcional.
        $lock = \Mockery::mock();
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->andReturn(true);

        Cache::shouldReceive('lock')->andReturn($lock);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key) => $key === 'classificacao_politica')
            ->andThrow(new \RuntimeException($mensagemSensivel));

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/me/estrategia/lideranca/', [
            'id_municipio' => $candidato->id_municipio,
            'aliados' => [$candidato->sequencial_candidato],
            'oposicao' => [],
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Erro ao cadastrar lideranças políticas. Tente novamente.',
        ]);
        $response->assertJsonMissingPath('message');
        $this->assertStringNotContainsString($mensagemSensivel, $response->getContent());
    }

    public function test_usuario_de_outro_role_nao_autorizado_nao_consegue_cadastrar(): void
    {
        $candidato = $this->buscarCandidatoDeTeste();

        $deputado = $this->criarDeputado('Deputado Sem Permissao');

        // Usuário sem nenhuma role (admin/manager/cabinet) não pode criar lideranças.
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/me/estrategia/lideranca/', [
            'id_municipio' => $candidato->id_municipio,
            'aliados' => [$candidato->sequencial_candidato],
            'oposicao' => [],
        ]);

        $response->assertStatus(403);
    }
}
