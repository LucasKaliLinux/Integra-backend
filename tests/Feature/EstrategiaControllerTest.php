<?php

namespace Tests\Feature;

use App\Models\Deputado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstrategiaControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const ANO_BASE = 2022;

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

    private function criarUsuarioGabinete(Deputado $deputado, bool $ativo = true): User
    {
        return User::factory()->create([
            'deputado_id' => $deputado->id,
            'ativo' => $ativo,
        ]);
    }

    /**
     * Insere um município fictício (fora da faixa de IDs reais do IBGE
     * usada pelos municípios da Bahia) com dados de eleitorado e votação
     * do deputado autenticado, para exercitar autoSelect() de ponta a
     * ponta contra as tabelas reais `municipios`/`eleitores`/`votacao`.
     */
    private function inserirMunicipioComVotos(Deputado $deputado, int $idMunicipio, int $votos, int $totalEleitores): void
    {
        DB::table('municipios')->insert([
            'id_municipio' => $idMunicipio,
            'nome' => 'Município Teste '.$idMunicipio,
            'populacao' => $totalEleitores * 2,
        ]);

        DB::table('eleitores')->insert([
            'id_municipio' => $idMunicipio,
            'ano' => self::ANO_BASE,
            'total_eleitores' => $totalEleitores,
        ]);

        DB::table('votacao')->insert([
            'ano' => self::ANO_BASE,
            'turno' => 1,
            'tipo_eleicao' => 'ordinaria',
            'id_eleicao' => $idMunicipio,
            'id_municipio' => $idMunicipio,
            'cargo' => 'deputado estadual',
            'sigla_partido' => 'AAA',
            'sequencial_candidato' => $idMunicipio,
            'titulo_eleitoral_candidato' => $deputado->titulo_eleitoral,
            'numero_candidato' => 12345,
            'votos' => $votos,
            'resultado' => 'eleito',
        ]);
    }

    public function test_ranking_usa_percentual_de_penetracao_e_nao_votos_absolutos(): void
    {
        $deputado = $this->criarDeputado('Deputado Ranking');
        $user = $this->criarUsuarioGabinete($deputado);

        // Município X: mais votos absolutos, porém percentual menor (10%).
        $this->inserirMunicipioComVotos($deputado, 9800001, 200, 2000);
        // Município Z: menos votos absolutos, porém percentual maior (15%).
        $this->inserirMunicipioComVotos($deputado, 9800002, 150, 1000);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto');

        $response->assertOk();

        $municipios = $response->json('municipios_selecionados');

        $this->assertCount(2, $municipios);
        // Município Z (percentual 15%, votos 150) deve vir antes do
        // Município X (percentual 10%, votos 200): a fórmula antiga de
        // score era equivalente a ordenar por votos absolutos e colocaria
        // X primeiro — essa asserção prova que o bug foi corrigido.
        $this->assertSame(9800002, $municipios[0]['id_municipio']);
        $this->assertSame(150, $municipios[0]['votos']);
        $this->assertEquals(15.0, $municipios[0]['percentual_votos']);

        $this->assertSame(9800001, $municipios[1]['id_municipio']);
        $this->assertSame(200, $municipios[1]['votos']);
        $this->assertEquals(10.0, $municipios[1]['percentual_votos']);
    }

    public function test_desempate_por_votos_absolutos_quando_percentual_e_igual(): void
    {
        $deputado = $this->criarDeputado('Deputado Desempate');
        $user = $this->criarUsuarioGabinete($deputado);

        // Ambos com percentual de 10%, mas votos absolutos diferentes.
        $this->inserirMunicipioComVotos($deputado, 9800101, 100, 1000);
        $this->inserirMunicipioComVotos($deputado, 9800102, 500, 5000);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto');

        $response->assertOk();

        $municipios = $response->json('municipios_selecionados');

        $this->assertCount(2, $municipios);
        $this->assertSame(9800102, $municipios[0]['id_municipio']);
        $this->assertSame(9800101, $municipios[1]['id_municipio']);
    }

    public function test_municipio_abaixo_do_corte_minimo_e_excluido(): void
    {
        $deputado = $this->criarDeputado('Deputado Corte Minimo');
        $user = $this->criarUsuarioGabinete($deputado);

        // 1% de penetração eleitoral, abaixo do corte mínimo de 2%.
        $this->inserirMunicipioComVotos($deputado, 9800201, 100, 10000);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto');

        $response->assertOk();
        $this->assertCount(0, $response->json('municipios_selecionados'));
    }

    public function test_quantidade_padrao_e_30_quando_parametro_omitido(): void
    {
        $deputado = $this->criarDeputado('Deputado Quantidade Padrao');
        $user = $this->criarUsuarioGabinete($deputado);

        // 35 municípios elegíveis, acima do default de 30.
        for ($i = 1; $i <= 35; $i++) {
            $this->inserirMunicipioComVotos($deputado, 9800300 + $i, 100 + $i, 1000);
        }

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto');

        $response->assertOk();
        $response->assertJsonPath('quantidade', 30);
        $this->assertCount(30, $response->json('municipios_selecionados'));
    }

    public function test_parametro_quantidade_e_respeitado(): void
    {
        $deputado = $this->criarDeputado('Deputado Quantidade Custom');
        $user = $this->criarUsuarioGabinete($deputado);

        for ($i = 1; $i <= 10; $i++) {
            $this->inserirMunicipioComVotos($deputado, 9800400 + $i, 100 + $i, 1000);
        }

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto?quantidade=5');

        $response->assertOk();
        $response->assertJsonPath('quantidade', 5);
        $this->assertCount(5, $response->json('municipios_selecionados'));
    }

    public function test_resposta_inclui_percentual_votos_e_corte_minimo_percentual(): void
    {
        $deputado = $this->criarDeputado('Deputado Contrato Resposta');
        $user = $this->criarUsuarioGabinete($deputado);

        $this->inserirMunicipioComVotos($deputado, 9800501, 100, 1000);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto');

        $response->assertOk();
        $this->assertEquals(2.0, $response->json('corte_minimo_percentual'));
        $this->assertEquals(10.0, $response->json('municipios_selecionados.0.percentual_votos'));
        $response->assertJsonStructure([
            'message',
            'ano_base',
            'quantidade',
            'corte_minimo_percentual',
            'municipios_selecionados' => [
                '*' => ['id_municipio', 'votos', 'total_eleitores', 'percentual_votos'],
            ],
        ]);
    }

    public function test_quantidade_invalida_retorna_422(): void
    {
        $deputado = $this->criarDeputado('Deputado Quantidade Invalida');
        $user = $this->criarUsuarioGabinete($deputado);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/me/estrategia/municipios/auto?quantidade=1')
            ->assertStatus(422);

        $this->getJson('/api/me/estrategia/municipios/auto?quantidade=1000')
            ->assertStatus(422);

        $this->getJson('/api/me/estrategia/municipios/auto?quantidade=abc')
            ->assertStatus(422);
    }

    public function test_usuario_sem_deputado_vinculado_nao_acessa(): void
    {
        $user = User::factory()->create([
            'deputado_id' => null,
            'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/me/estrategia/municipios/auto');

        $response->assertStatus(403);
    }
}
