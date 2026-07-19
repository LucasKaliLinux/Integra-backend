<?php

namespace Tests\Feature;

use App\Models\CargoLideranca;
use App\Models\Deputado;
use App\Models\Lideranca;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AniversarioControllerTest extends TestCase
{
    use DatabaseTransactions;

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

    private function criarLideranca(Deputado $deputado, User $user, string $nome, ?string $dataNascimento, string $alinhamento = 'aliado'): Lideranca
    {
        $municipio = Municipio::query()->first();
        $cargo = CargoLideranca::query()->first();

        return Lideranca::create([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'classificacao_id' => $cargo->classificacao_id,
            'funcao_id' => $cargo->id,
            'nome' => $nome,
            'telefone' => '11999990000',
            'data_nascimento' => $dataNascimento,
            'alinhamento' => $alinhamento,
        ]);
    }

    public function test_lista_aniversarios_apenas_do_proprio_deputado_ordenados_por_dias_restantes(): void
    {
        $deputado = $this->criarDeputado('Deputado Aniversario');
        $outroDeputado = $this->criarDeputado('Outro Deputado');

        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);
        $outroUser = User::factory()->create(['deputado_id' => $outroDeputado->id, 'ativo' => true]);

        $hoje = now();

        // Aniversário hoje (dias_ate_aniversario = 0)
        $liderancaHoje = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Hoje',
            $hoje->copy()->subYears(40)->format('Y-m-d')
        );

        // Aniversário daqui a 10 dias
        $liderancaDaqui10Dias = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Daqui a 10 dias',
            $hoje->copy()->subYears(30)->addDays(10)->format('Y-m-d')
        );

        // Sem data de nascimento — não deve aparecer
        $this->criarLideranca($deputado, $user, 'Sem Nascimento', null);

        // Lideranca de outro deputado — não deve aparecer (isolamento de tenant)
        $this->criarLideranca(
            $outroDeputado,
            $outroUser,
            'Lideranca Outro Deputado',
            $hoje->copy()->subYears(25)->format('Y-m-d')
        );

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/aniversarios');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');

        $this->assertTrue($nomes->contains('Lideranca Hoje'));
        $this->assertTrue($nomes->contains('Lideranca Daqui a 10 dias'));
        $this->assertFalse($nomes->contains('Sem Nascimento'));
        $this->assertFalse($nomes->contains('Lideranca Outro Deputado'));

        // Ordenado por dias_ate_aniversario ascendente: "hoje" (0) antes de "daqui a 10 dias"
        $indiceHoje = $nomes->search('Lideranca Hoje');
        $indiceDaqui10Dias = $nomes->search('Lideranca Daqui a 10 dias');
        $this->assertLessThan($indiceDaqui10Dias, $indiceHoje);

        $itemHoje = collect($response->json('data'))->firstWhere('nome', 'Lideranca Hoje');
        $this->assertSame(0, $itemHoje['dias_ate_aniversario']);
        $this->assertSame(40, $itemHoje['idade_completar']);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'nome', 'telefone', 'data_nascimento',
                    'proxima_data_aniversario', 'dias_ate_aniversario',
                    'idade_completar', 'alinhamento', 'municipio', 'classificacao', 'funcao',
                ],
            ],
            'links',
            'meta',
            'resumo' => ['hoje', 'proximos_7_dias', 'este_mes', 'mes_que_vem'],
        ]);
    }

    public function test_lideranca_de_outro_deputado_nao_aparece_no_resultado(): void
    {
        $deputado = $this->criarDeputado('Deputado Isolamento');
        $outroDeputado = $this->criarDeputado('Outro Deputado Isolamento');

        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);
        $outroUser = User::factory()->create(['deputado_id' => $outroDeputado->id, 'ativo' => true]);

        $this->criarLideranca($outroDeputado, $outroUser, 'Lideranca Alheia', now()->subYears(50)->format('Y-m-d'));

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/aniversarios');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_filtro_periodo_hoje_retorna_apenas_quem_faz_aniversario_hoje(): void
    {
        $deputado = $this->criarDeputado('Deputado Filtro Periodo Hoje');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $hoje = now();

        $liderancaHoje = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Hoje',
            $hoje->copy()->subYears(40)->format('Y-m-d')
        );

        $liderancaDaqui10Dias = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Daqui a 10 dias',
            $hoje->copy()->subYears(30)->addDays(10)->format('Y-m-d')
        );

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/aniversarios?periodo=hoje');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');

        $this->assertTrue($nomes->contains('Lideranca Hoje'));
        $this->assertFalse($nomes->contains('Lideranca Daqui a 10 dias'));
    }

    public function test_filtro_periodo_7_dias_inclui_hoje_e_proximos_7_dias_e_exclui_fora_da_janela(): void
    {
        $deputado = $this->criarDeputado('Deputado Filtro Periodo 7 Dias');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $hoje = now();

        $liderancaHoje = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Hoje',
            $hoje->copy()->subYears(40)->format('Y-m-d')
        );

        $liderancaDaqui5Dias = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Daqui a 5 dias',
            $hoje->copy()->subYears(35)->addDays(5)->format('Y-m-d')
        );

        $liderancaDaqui20Dias = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Daqui a 20 dias',
            $hoje->copy()->subYears(30)->addDays(20)->format('Y-m-d')
        );

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/aniversarios?periodo=7_dias');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');

        $this->assertTrue($nomes->contains('Lideranca Hoje'));
        $this->assertTrue($nomes->contains('Lideranca Daqui a 5 dias'));
        $this->assertFalse($nomes->contains('Lideranca Daqui a 20 dias'));
    }

    public function test_filtro_periodo_este_ano_inclui_quem_ainda_vai_fazer_aniversario_neste_ano_civil(): void
    {
        $deputado = $this->criarDeputado('Deputado Filtro Periodo Este Ano');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $hoje = now();

        // Próximo aniversário ainda este ano (daqui a 20 dias, sem cruzar 31/12).
        $liderancaAindaEsteAno = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Ainda Este Ano',
            $hoje->copy()->subYears(30)->addDays(20)->format('Y-m-d')
        );

        // Já fez aniversário este ano — o próximo cai no ano que vem.
        $liderancaJaFezEsteAno = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Ja Fez Este Ano',
            $hoje->copy()->subYears(30)->subDays(20)->format('Y-m-d')
        );

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/aniversarios?periodo=este_ano');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');

        $this->assertTrue($nomes->contains('Lideranca Ainda Este Ano'));
        $this->assertFalse($nomes->contains('Lideranca Ja Fez Este Ano'));
    }

    public function test_periodo_ausente_ou_invalido_nao_filtra_nada(): void
    {
        $deputado = $this->criarDeputado('Deputado Sem Filtro Periodo');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $hoje = now();

        $liderancaHoje = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Hoje',
            $hoje->copy()->subYears(40)->format('Y-m-d')
        );

        $liderancaDaqui100Dias = $this->criarLideranca(
            $deputado,
            $user,
            'Lideranca Daqui a 100 dias',
            $hoje->copy()->subYears(30)->addDays(100)->format('Y-m-d')
        );

        $this->actingAs($user, 'sanctum');

        $semPeriodo = $this->getJson('/api/me/aniversarios');
        $semPeriodo->assertOk();
        $nomesSemPeriodo = collect($semPeriodo->json('data'))->pluck('nome');
        $this->assertTrue($nomesSemPeriodo->contains('Lideranca Hoje'));
        $this->assertTrue($nomesSemPeriodo->contains('Lideranca Daqui a 100 dias'));

        $periodoInvalido = $this->getJson('/api/me/aniversarios?periodo=valor_invalido');
        $periodoInvalido->assertOk();
        $nomesPeriodoInvalido = collect($periodoInvalido->json('data'))->pluck('nome');
        $this->assertTrue($nomesPeriodoInvalido->contains('Lideranca Hoje'));
        $this->assertTrue($nomesPeriodoInvalido->contains('Lideranca Daqui a 100 dias'));
    }

    public function test_filtro_periodo_nao_altera_os_valores_do_resumo(): void
    {
        $deputado = $this->criarDeputado('Deputado Resumo Fixo');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $hoje = now();

        $this->criarLideranca($deputado, $user, 'Lideranca Hoje', $hoje->copy()->subYears(40)->format('Y-m-d'));
        $this->criarLideranca($deputado, $user, 'Lideranca Daqui a 5 dias', $hoje->copy()->subYears(35)->addDays(5)->format('Y-m-d'));
        $this->criarLideranca($deputado, $user, 'Lideranca Daqui a 100 dias', $hoje->copy()->subYears(30)->addDays(100)->format('Y-m-d'));

        $this->actingAs($user, 'sanctum');

        $semPeriodo = $this->getJson('/api/me/aniversarios');
        $semPeriodo->assertOk();
        $resumoSemPeriodo = $semPeriodo->json('resumo');

        $comPeriodoHoje = $this->getJson('/api/me/aniversarios?periodo=hoje');
        $comPeriodoHoje->assertOk();
        $resumoComPeriodoHoje = $comPeriodoHoje->json('resumo');

        $comPeriodoEsteAno = $this->getJson('/api/me/aniversarios?periodo=este_ano');
        $comPeriodoEsteAno->assertOk();
        $resumoComPeriodoEsteAno = $comPeriodoEsteAno->json('resumo');

        $this->assertSame($resumoSemPeriodo, $resumoComPeriodoHoje);
        $this->assertSame($resumoSemPeriodo, $resumoComPeriodoEsteAno);
    }
}
