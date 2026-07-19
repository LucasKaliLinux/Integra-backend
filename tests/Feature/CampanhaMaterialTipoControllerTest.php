<?php

namespace Tests\Feature;

use App\Models\CampanhaMaterial;
use App\Models\CampanhaMaterialTipo;
use App\Models\Deputado;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampanhaMaterialTipoControllerTest extends TestCase
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

    private function criarTipo(Deputado $deputado, array $attrs = []): CampanhaMaterialTipo
    {
        // forceCreate: deputado_id não é fillable (atribuído no controller).
        return CampanhaMaterialTipo::forceCreate(array_merge([
            'deputado_id' => $deputado->id,
            'nome' => 'Tipo '.uniqid(),
            'ativo' => true,
        ], $attrs));
    }

    public function test_crud_basico_de_tipo_de_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo CRUD');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // CREATE
        $store = $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Santinhos',
            'categoria' => 'impresso',
            'unidade' => 'un',
        ]);
        $store->assertCreated();
        $id = $store->json('data.id');

        $this->assertDatabaseHas('campanha_material_tipos', [
            'id' => $id,
            'deputado_id' => $deputado->id,
            'nome' => 'Santinhos',
            'categoria' => 'impresso',
            'unidade' => 'un',
            'ativo' => true,
        ]);

        // INDEX
        $index = $this->getJson('/api/campanha/tipos-material');
        $index->assertOk();
        $this->assertTrue(collect($index->json('data'))->pluck('id')->contains($id));

        // SHOW
        $show = $this->getJson("/api/campanha/tipos-material/{$id}");
        $show->assertOk();
        $show->assertJsonPath('data.nome', 'Santinhos');
        $show->assertJsonPath('data.ativo', true);

        // UPDATE (renomear e inativar)
        $update = $this->putJson("/api/campanha/tipos-material/{$id}", [
            'nome' => 'Santinhos Atualizado',
            'categoria' => 'impresso',
            'unidade' => 'un',
            'ativo' => false,
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.nome', 'Santinhos Atualizado');
        $update->assertJsonPath('data.ativo', false);

        // DESTROY
        $destroy = $this->deleteJson("/api/campanha/tipos-material/{$id}");
        $destroy->assertOk();
        $destroy->assertJson(['message' => 'Tipo de material removido com sucesso.']);
        $this->assertDatabaseMissing('campanha_material_tipos', ['id' => $id]);
    }

    public function test_escopo_por_deputado_nao_ve_nem_altera_tipo_alheio(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Escopo A');
        $outroDeputado = $this->criarDeputado('Deputado Tipo Escopo B');

        $user = $this->criarUsuario($deputado);
        $tipoAlheio = $this->criarTipo($outroDeputado);

        $this->actingAs($user, 'sanctum');

        $index = $this->getJson('/api/campanha/tipos-material?all=true');
        $index->assertOk();
        $this->assertFalse(collect($index->json('data'))->pluck('id')->contains($tipoAlheio->id));

        $this->getJson("/api/campanha/tipos-material/{$tipoAlheio->id}")->assertNotFound();
        $this->putJson("/api/campanha/tipos-material/{$tipoAlheio->id}", [
            'nome' => 'X',
            'categoria' => 'outro',
            'unidade' => 'un',
        ])->assertNotFound();
        $this->deleteJson("/api/campanha/tipos-material/{$tipoAlheio->id}")->assertNotFound();

        $this->assertDatabaseHas('campanha_material_tipos', ['id' => $tipoAlheio->id]);
    }

    public function test_usuario_sem_role_nao_pode_criar(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Sem Role');
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Nao Deve Criar',
            'categoria' => 'outro',
            'unidade' => 'un',
        ])->assertForbidden();
    }

    public function test_cabinet_pode_criar_atualizar_e_excluir(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Cabinet');
        $user = $this->criarUsuario($deputado, 'cabinet');

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Bonés',
            'categoria' => 'vestuario',
            'unidade' => 'un',
        ]);
        $store->assertCreated();
        $id = $store->json('data.id');

        $this->putJson("/api/campanha/tipos-material/{$id}", [
            'nome' => 'Bonés Premium',
            'categoria' => 'vestuario',
            'unidade' => 'un',
        ])->assertOk();
        $this->deleteJson("/api/campanha/tipos-material/{$id}")->assertOk();
    }

    public function test_nao_exclui_tipo_referenciado_em_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Exclusao Bloqueada');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipo($deputado);
        $municipio = Municipio::query()->first();

        CampanhaMaterial::forceCreate([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 100,
            'status' => 'solicitado',
            'data' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->deleteJson("/api/campanha/tipos-material/{$tipo->id}");

        $response->assertStatus(422);
        $this->assertStringContainsString('Inative-o', $response->json('error'));
        $this->assertDatabaseHas('campanha_material_tipos', ['id' => $tipo->id]);
    }

    public function test_permite_inativar_tipo_referenciado_em_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Inativacao');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipo($deputado);
        $municipio = Municipio::query()->first();

        CampanhaMaterial::forceCreate([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 100,
            'status' => 'solicitado',
            'data' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($user, 'sanctum');

        $this->putJson("/api/campanha/tipos-material/{$tipo->id}", [
            'nome' => $tipo->nome,
            'categoria' => 'outro',
            'unidade' => 'un',
            'ativo' => false,
        ])->assertOk();

        $this->assertDatabaseHas('campanha_material_tipos', ['id' => $tipo->id, 'ativo' => false]);
    }

    public function test_validacoes_do_tipo_de_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Validacao');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/tipos-material', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome']);

        $this->postJson('/api/campanha/tipos-material', [
            'nome' => str_repeat('a', 256),
        ])->assertStatus(422)->assertJsonValidationErrors(['nome']);
    }

    public function test_rejeita_nome_duplicado_para_o_mesmo_deputado(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Duplicado');
        $user = $this->criarUsuario($deputado);
        $this->criarTipo($deputado, ['nome' => 'Santinhos']);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/tipos-material', ['nome' => 'Santinhos'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome']);
    }

    public function test_permite_nome_duplicado_entre_deputados_diferentes(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Nome A');
        $outroDeputado = $this->criarDeputado('Deputado Tipo Nome B');
        $this->criarTipo($outroDeputado, ['nome' => 'Santinhos']);

        $user = $this->criarUsuario($deputado);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Santinhos',
            'categoria' => 'impresso',
            'unidade' => 'un',
        ])->assertCreated();
    }

    public function test_query_params_invalidos_no_index_retornam_422_e_nao_500(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Query Params');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/campanha/tipos-material?per_page=0')->assertStatus(422);
        $this->getJson('/api/campanha/tipos-material?per_page=-1')->assertStatus(422);
        $this->getJson('/api/campanha/tipos-material?search[]=x')->assertStatus(422);

        $this->getJson('/api/campanha/tipos-material?per_page=1&search=abc')->assertOk();
        $this->getJson('/api/campanha/tipos-material?per_page=')->assertOk();
    }

    public function test_index_retorna_materiais_count_por_tipo(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Contagem');
        $user = $this->criarUsuario($deputado);
        $tipoComUso = $this->criarTipo($deputado);
        $tipoSemUso = $this->criarTipo($deputado);
        $municipio = Municipio::query()->first();

        CampanhaMaterial::forceCreate([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipoComUso->id,
            'quantidade' => 10,
            'status' => 'solicitado',
            'data' => now()->addDays(5)->format('Y-m-d'),
        ]);
        CampanhaMaterial::forceCreate([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipoComUso->id,
            'quantidade' => 5,
            'status' => 'solicitado',
            'data' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($user, 'sanctum');

        $index = $this->getJson('/api/campanha/tipos-material?all=true');
        $index->assertOk();

        $itens = collect($index->json('data'))->keyBy('id');
        $this->assertSame(2, $itens[$tipoComUso->id]['materiais_count']);
        $this->assertSame(0, $itens[$tipoSemUso->id]['materiais_count']);
    }

    public function test_deputado_id_enviado_no_payload_e_ignorado(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Spoof A');
        $outroDeputado = $this->criarDeputado('Deputado Tipo Spoof B');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Tentativa Spoof',
            'categoria' => 'outro',
            'unidade' => 'un',
            'deputado_id' => $outroDeputado->id,
        ]);

        $store->assertCreated();
        $this->assertDatabaseHas('campanha_material_tipos', [
            'id' => $store->json('data.id'),
            'deputado_id' => $deputado->id,
        ]);
    }

    public function test_cria_e_atualiza_tipo_com_categoria_unidade_e_variantes(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Variantes');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // CREATE com variantes.
        $store = $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Camisetas',
            'categoria' => 'vestuario',
            'unidade' => 'peça',
            'variantes' => ['P', 'M', 'G'],
        ]);
        $store->assertCreated();
        $id = $store->json('data.id');

        $store->assertJsonPath('data.categoria', 'vestuario');
        $store->assertJsonPath('data.unidade', 'peça');
        $store->assertJsonPath('data.variantes', ['P', 'M', 'G']);

        // SHOW expõe os mesmos campos.
        $show = $this->getJson("/api/campanha/tipos-material/{$id}");
        $show->assertOk();
        $show->assertJsonPath('data.categoria', 'vestuario');
        $show->assertJsonPath('data.unidade', 'peça');
        $show->assertJsonPath('data.variantes', ['P', 'M', 'G']);

        // UPDATE trocando categoria/unidade e variantes.
        $update = $this->putJson("/api/campanha/tipos-material/{$id}", [
            'nome' => 'Camisetas',
            'categoria' => 'brinde',
            'unidade' => 'un',
            'variantes' => ['Único'],
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.categoria', 'brinde');
        $update->assertJsonPath('data.unidade', 'un');
        $update->assertJsonPath('data.variantes', ['Único']);
    }

    public function test_tipo_sem_variantes_retorna_array_vazio(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Sem Variantes');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Faixas',
            'categoria' => 'sinalizacao',
            'unidade' => 'm',
        ]);
        $store->assertCreated();
        $store->assertJsonPath('data.variantes', []);
    }

    public function test_rejeita_categoria_invalida(): void
    {
        $deputado = $this->criarDeputado('Deputado Categoria Invalida');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        // Ausente.
        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Sem Categoria',
            'unidade' => 'un',
        ])->assertStatus(422)->assertJsonValidationErrors(['categoria']);

        // Fora do enum fixo.
        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Categoria Errada',
            'categoria' => 'panfleto',
            'unidade' => 'un',
        ])->assertStatus(422)->assertJsonValidationErrors(['categoria']);
    }

    public function test_rejeita_unidade_ausente_ou_gigante(): void
    {
        $deputado = $this->criarDeputado('Deputado Unidade');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Sem Unidade',
            'categoria' => 'outro',
        ])->assertStatus(422)->assertJsonValidationErrors(['unidade']);

        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Unidade Gigante',
            'categoria' => 'outro',
            'unidade' => str_repeat('a', 51),
        ])->assertStatus(422)->assertJsonValidationErrors(['unidade']);
    }

    public function test_rejeita_variantes_duplicadas(): void
    {
        $deputado = $this->criarDeputado('Deputado Variantes Dup');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Bonés Tamanhos',
            'categoria' => 'vestuario',
            'unidade' => 'un',
            'variantes' => ['Único', 'Único'],
        ])->assertStatus(422)->assertJsonValidationErrors(['variantes.0']);
    }

    public function test_normaliza_variantes_removendo_vazias_e_espacos(): void
    {
        $deputado = $this->criarDeputado('Deputado Variantes Normaliza');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/tipos-material', [
            'nome' => 'Adesivos Tamanhos',
            'categoria' => 'impresso',
            'unidade' => 'un',
            'variantes' => ['  Pequeno  ', '', 'Grande', '   '],
        ]);

        $store->assertCreated();
        $store->assertJsonPath('data.variantes', ['Pequeno', 'Grande']);
    }
}
