<?php

namespace Tests\Feature;

use App\Models\CampanhaMaterial;
use App\Models\CampanhaMaterialTipo;
use App\Models\CampanhaPolitico;
use App\Models\Deputado;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampanhaPoliticoControllerTest extends TestCase
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

    private function criarUsuario(Deputado $deputado, string $role = 'admin'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function criarPolitico(Deputado $deputado, string $cargo = 'estadual', array $attrs = []): CampanhaPolitico
    {
        // forceCreate: deputado_id não é fillable (atribuído no controller).
        return CampanhaPolitico::forceCreate(array_merge([
            'deputado_id' => $deputado->id,
            'nome' => 'Politico '.uniqid(),
            'nome_urna' => 'Urna '.uniqid(),
            'numero_eleitoral' => (string) fake()->numberBetween(10000, 99999),
            'cargo' => $cargo,
            'partido' => 'AAA',
        ], $attrs));
    }

    public function test_crud_basico_de_politico(): void
    {
        $deputado = $this->criarDeputado('Deputado Campanha Politico');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // CREATE
        $store = $this->postJson('/api/campanha/politicos', [
            'nome' => 'Fulano de Tal',
            'nome_urna' => 'Fulano',
            'numero_eleitoral' => '01234',
            'cargo' => 'senador',
            'partido' => 'AAA',
            'cor_principal' => '#1A2B3C',
            'slogan' => 'Pra frente',
        ]);

        $store->assertCreated();
        $id = $store->json('data.id');

        $this->assertDatabaseHas('campanha_politicos', [
            'id' => $id,
            'deputado_id' => $deputado->id,
            'numero_eleitoral' => '01234',
            'cargo' => 'senador',
        ]);

        // INDEX
        $index = $this->getJson('/api/campanha/politicos');
        $index->assertOk();
        $this->assertTrue(collect($index->json('data'))->pluck('id')->contains($id));

        // SHOW
        $show = $this->getJson("/api/campanha/politicos/{$id}");
        $show->assertOk();
        $show->assertJsonPath('data.nome', 'Fulano de Tal');
        $show->assertJsonPath('data.cor_principal', '#1A2B3C');
        $show->assertJsonPath('data.ativo', true);

        // UPDATE
        $update = $this->putJson("/api/campanha/politicos/{$id}", [
            'nome' => 'Fulano de Tal Atualizado',
            'nome_urna' => 'Fulano',
            'numero_eleitoral' => '01234',
            'cargo' => 'senador',
            'partido' => 'BBB',
            'ativo' => false,
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.partido', 'BBB');
        $update->assertJsonPath('data.ativo', false);

        // DESTROY
        $destroy = $this->deleteJson("/api/campanha/politicos/{$id}");
        $destroy->assertOk();
        $destroy->assertJson(['message' => 'Político removido com sucesso.']);
        $this->assertDatabaseMissing('campanha_politicos', ['id' => $id]);
    }

    public function test_escopo_por_deputado_nao_ve_nem_altera_politico_alheio(): void
    {
        $deputado = $this->criarDeputado('Deputado Escopo A');
        $outroDeputado = $this->criarDeputado('Deputado Escopo B');

        $user = $this->criarUsuario($deputado);
        $politicoAlheio = $this->criarPolitico($outroDeputado, 'governador');

        $this->actingAs($user, 'sanctum');

        // Index não lista político de outro deputado
        $index = $this->getJson('/api/campanha/politicos?all=true');
        $index->assertOk();
        $this->assertFalse(collect($index->json('data'))->pluck('id')->contains($politicoAlheio->id));

        // Show / update / destroy de registro alheio → 404 (escopo antes da policy)
        $this->getJson("/api/campanha/politicos/{$politicoAlheio->id}")->assertNotFound();
        $this->putJson("/api/campanha/politicos/{$politicoAlheio->id}", [
            'nome' => 'X',
            'nome_urna' => 'X',
            'numero_eleitoral' => '99',
            'cargo' => 'governador',
            'partido' => 'ZZZ',
        ])->assertNotFound();
        $this->deleteJson("/api/campanha/politicos/{$politicoAlheio->id}")->assertNotFound();

        $this->assertDatabaseHas('campanha_politicos', ['id' => $politicoAlheio->id]);
    }

    public function test_cabinet_pode_criar_atualizar_e_excluir(): void
    {
        $deputado = $this->criarDeputado('Deputado Cabinet');
        $user = $this->criarUsuario($deputado, 'cabinet');

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/politicos', [
            'nome' => 'Politico do Cabinet',
            'nome_urna' => 'Cabinete',
            'numero_eleitoral' => '55555',
            'cargo' => 'federal',
            'partido' => 'CCC',
        ]);
        $store->assertCreated();

        $id = $store->json('data.id');

        $this->putJson("/api/campanha/politicos/{$id}", [
            'nome' => 'Politico do Cabinet',
            'nome_urna' => 'Cabinete',
            'numero_eleitoral' => '55555',
            'cargo' => 'federal',
            'partido' => 'DDD',
        ])->assertOk();

        $this->deleteJson("/api/campanha/politicos/{$id}")->assertOk();
    }

    public function test_usuario_sem_role_nao_pode_criar(): void
    {
        $deputado = $this->criarDeputado('Deputado Sem Role');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/politicos', [
            'nome' => 'Nao Deve Criar',
            'nome_urna' => 'Nada',
            'numero_eleitoral' => '11111',
            'cargo' => 'estadual',
            'partido' => 'AAA',
        ])->assertForbidden();
    }

    public function test_cargo_invalido_e_cor_invalida_sao_rejeitados(): void
    {
        $deputado = $this->criarDeputado('Deputado Validacao');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/campanha/politicos', [
            'nome' => 'Invalido',
            'nome_urna' => 'Invalido',
            'numero_eleitoral' => '123',
            'cargo' => 'vereador',
            'partido' => 'AAA',
            'cor_principal' => 'azul',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cargo', 'cor_principal']);
    }

    public function test_nao_exclui_politico_referenciado_em_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Exclusao Bloqueada');
        $user = $this->criarUsuario($deputado);
        $politico = $this->criarPolitico($deputado, 'senador');
        $municipio = Municipio::query()->first();
        $tipo = CampanhaMaterialTipo::forceCreate(['deputado_id' => $deputado->id, 'nome' => 'Santinhos']);

        CampanhaMaterial::forceCreate([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 1000,
            'status' => 'solicitado',
            'politico_senador_id' => $politico->id,
            'data' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->deleteJson("/api/campanha/politicos/{$politico->id}");

        $response->assertStatus(422);
        $this->assertStringContainsString('Inative-o', $response->json('error'));
        $this->assertDatabaseHas('campanha_politicos', ['id' => $politico->id]);
    }

    public function test_rejeita_foto_url_com_scheme_diferente_de_http_https(): void
    {
        $deputado = $this->criarDeputado('Deputado Scheme Foto');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/campanha/politicos', [
            'nome' => 'Scheme Invalido',
            'nome_urna' => 'Scheme',
            'numero_eleitoral' => '12345',
            'cargo' => 'estadual',
            'partido' => 'AAA',
            'foto_url' => 'ftp://exemplo.com/foto.png',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['foto_url']);

        // https continua aceito.
        $this->postJson('/api/campanha/politicos', [
            'nome' => 'Scheme Valido',
            'nome_urna' => 'Scheme Ok',
            'numero_eleitoral' => '12346',
            'cargo' => 'estadual',
            'partido' => 'AAA',
            'foto_url' => 'https://exemplo.com/foto.png',
        ])->assertCreated();
    }

    public function test_query_params_invalidos_no_index_retornam_422_e_nao_500(): void
    {
        $deputado = $this->criarDeputado('Deputado Query Params');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/campanha/politicos?per_page=0')->assertStatus(422);
        $this->getJson('/api/campanha/politicos?per_page=-1')->assertStatus(422);
        $this->getJson('/api/campanha/politicos?per_page=abc')->assertStatus(422);
        $this->getJson('/api/campanha/politicos?search[]=x')->assertStatus(422);
        $this->getJson('/api/campanha/politicos?cargo[]=estadual')->assertStatus(422);

        // Valores válidos (ou vazios, que caem no padrão) seguem funcionando.
        $this->getJson('/api/campanha/politicos?per_page=1&search=abc')->assertOk();
        $this->getJson('/api/campanha/politicos?per_page=')->assertOk();
    }

    public function test_bloqueia_troca_de_cargo_de_politico_referenciado_em_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Troca Cargo Bloqueada');
        $user = $this->criarUsuario($deputado);
        $politico = $this->criarPolitico($deputado, 'senador');
        $municipio = Municipio::query()->first();
        $tipo = CampanhaMaterialTipo::forceCreate(['deputado_id' => $deputado->id, 'nome' => 'Santinhos']);

        CampanhaMaterial::forceCreate([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 500,
            'status' => 'solicitado',
            'politico_senador_id' => $politico->id,
            'data' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($user, 'sanctum');

        $payloadBase = [
            'nome' => $politico->nome,
            'nome_urna' => $politico->nome_urna,
            'numero_eleitoral' => $politico->numero_eleitoral,
            'partido' => $politico->partido,
        ];

        // Trocar o cargo com referência → 422.
        $bloqueado = $this->putJson(
            "/api/campanha/politicos/{$politico->id}",
            array_merge($payloadBase, ['cargo' => 'federal'])
        );
        $bloqueado->assertStatus(422);
        $this->assertStringContainsString('não pode ter o cargo alterado', $bloqueado->json('error'));
        $this->assertDatabaseHas('campanha_politicos', ['id' => $politico->id, 'cargo' => 'senador']);

        // Demais campos continuam editáveis mantendo o cargo.
        $this->putJson(
            "/api/campanha/politicos/{$politico->id}",
            array_merge($payloadBase, ['cargo' => 'senador', 'partido' => 'ZZZ'])
        )->assertOk();
        $this->assertDatabaseHas('campanha_politicos', ['id' => $politico->id, 'partido' => 'ZZZ']);
    }

    public function test_permite_troca_de_cargo_sem_referencias(): void
    {
        $deputado = $this->criarDeputado('Deputado Troca Cargo Livre');
        $user = $this->criarUsuario($deputado);
        $politico = $this->criarPolitico($deputado, 'senador');

        $this->actingAs($user, 'sanctum');

        $this->putJson("/api/campanha/politicos/{$politico->id}", [
            'nome' => $politico->nome,
            'nome_urna' => $politico->nome_urna,
            'numero_eleitoral' => $politico->numero_eleitoral,
            'cargo' => 'governador',
            'partido' => $politico->partido,
        ])->assertOk();

        $this->assertDatabaseHas('campanha_politicos', ['id' => $politico->id, 'cargo' => 'governador']);
    }

    public function test_deputado_id_enviado_no_payload_e_ignorado(): void
    {
        $deputado = $this->criarDeputado('Deputado Spoof A');
        $outroDeputado = $this->criarDeputado('Deputado Spoof B');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/politicos', [
            'nome' => 'Tentativa Spoof',
            'nome_urna' => 'Spoof',
            'numero_eleitoral' => '77777',
            'cargo' => 'estadual',
            'partido' => 'AAA',
            'deputado_id' => $outroDeputado->id,
        ]);

        $store->assertCreated();
        $this->assertDatabaseHas('campanha_politicos', [
            'id' => $store->json('data.id'),
            'deputado_id' => $deputado->id,
        ]);
    }

    public function test_filtros_de_cargo_e_busca(): void
    {
        $deputado = $this->criarDeputado('Deputado Filtros');
        $user = $this->criarUsuario($deputado);

        $senador = $this->criarPolitico($deputado, 'senador', ['nome' => 'Senador Buscavel Xyz']);
        $federal = $this->criarPolitico($deputado, 'federal', ['nome' => 'Federal Qualquer']);

        $this->actingAs($user, 'sanctum');

        $porCargo = $this->getJson('/api/campanha/politicos?all=true&cargo=senador');
        $porCargo->assertOk();
        $ids = collect($porCargo->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($senador->id));
        $this->assertFalse($ids->contains($federal->id));

        $porBusca = $this->getJson('/api/campanha/politicos?all=true&search=Buscavel Xyz');
        $porBusca->assertOk();
        $idsBusca = collect($porBusca->json('data'))->pluck('id');
        $this->assertTrue($idsBusca->contains($senador->id));
        $this->assertFalse($idsBusca->contains($federal->id));
    }
}
