<?php

namespace Tests\Feature;

use App\Models\CampanhaMaterial;
use App\Models\CampanhaMaterialTipo;
use App\Models\CampanhaPolitico;
use App\Models\Deputado;
use App\Models\Lideranca;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampanhaMaterialControllerTest extends TestCase
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

    private function criarTipoMaterial(Deputado $deputado, array $attrs = []): CampanhaMaterialTipo
    {
        // forceCreate: deputado_id não é fillable (atribuído no controller).
        return CampanhaMaterialTipo::forceCreate(array_merge([
            'deputado_id' => $deputado->id,
            'nome' => 'Tipo '.uniqid(),
            'ativo' => true,
        ], $attrs));
    }

    private function criarMaterial(Deputado $deputado, User $user, array $attrs = []): CampanhaMaterial
    {
        $municipio = Municipio::query()->first();

        if (! isset($attrs['tipo_material_id'])) {
            $attrs['tipo_material_id'] = $this->criarTipoMaterial($deputado)->id;
        }

        // forceCreate: deputado_id/user_id não são fillable (atribuídos no controller).
        return CampanhaMaterial::forceCreate(array_merge([
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'id_municipio' => $municipio->id_municipio,
            'quantidade' => 100,
            'status' => 'solicitado',
            'data' => now()->addDays(7)->format('Y-m-d'),
        ], $attrs));
    }

    public function test_crud_basico_de_material_com_composicao(): void
    {
        $deputado = $this->criarDeputado('Deputado Material CRUD');
        $user = $this->criarUsuario($deputado);
        $senador = $this->criarPolitico($deputado, 'senador', ['cor_principal' => '#FF0000']);
        $governador = $this->criarPolitico($deputado, 'governador');
        $tipoSantinhos = $this->criarTipoMaterial($deputado, ['nome' => 'Santinhos']);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        // CREATE
        $store = $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipoSantinhos->id,
            'quantidade' => 5000,
            'status' => 'solicitado',
            'data' => now()->addDays(30)->format('Y-m-d'),
            'politico_senador_id' => $senador->id,
            'politico_governador_id' => $governador->id,
            'observacao' => 'Entregar na sede',
        ]);

        $store->assertCreated();
        $id = $store->json('data.id');

        $this->assertDatabaseHas('campanha_materiais', [
            'id' => $id,
            'deputado_id' => $deputado->id,
            'user_id' => $user->id,
            'politico_senador_id' => $senador->id,
            'tipo_material_id' => $tipoSantinhos->id,
        ]);

        $store->assertJsonPath('data.composicao.senador.id', $senador->id);
        $store->assertJsonPath('data.composicao.senador.cor_principal', '#FF0000');
        $store->assertJsonPath('data.composicao.governador.id', $governador->id);
        $store->assertJsonPath('data.composicao.federal', null);
        $store->assertJsonPath('data.municipio.id_municipio', $municipio->id_municipio);
        $store->assertJsonPath('data.tipo_material.id', $tipoSantinhos->id);
        $store->assertJsonPath('data.tipo_material.nome', 'Santinhos');
        $store->assertJsonPath('data.user.id', $user->id);
        $store->assertJsonPath('data.data', now()->addDays(30)->format('d/m/Y'));

        // SHOW
        $show = $this->getJson("/api/campanha/materiais/{$id}");
        $show->assertOk();
        $show->assertJsonPath('data.tipo_material.nome', 'Santinhos');

        // UPDATE (transição livre de status + recebedor opcional).
        // Slots enviados como null são limpos; slots ausentes são mantidos.
        $update = $this->putJson("/api/campanha/materiais/{$id}", [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipoSantinhos->id,
            'quantidade' => 5000,
            'status' => 'entregue',
            'data' => now()->addDays(30)->format('Y-m-d'),
            'politico_senador_id' => $senador->id,
            'politico_governador_id' => null,
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.status', 'entregue');
        $update->assertJsonPath('data.composicao.governador', null);

        // DESTROY
        $destroy = $this->deleteJson("/api/campanha/materiais/{$id}");
        $destroy->assertOk();
        $destroy->assertJson(['message' => 'Material removido com sucesso.']);
        $this->assertDatabaseMissing('campanha_materiais', ['id' => $id]);
    }

    public function test_rejeita_politico_de_outro_deputado_no_slot(): void
    {
        $deputado = $this->criarDeputado('Deputado Slot Proprio');
        $outroDeputado = $this->criarDeputado('Deputado Slot Alheio');

        $user = $this->criarUsuario($deputado);
        $senadorAlheio = $this->criarPolitico($outroDeputado, 'senador');
        $tipo = $this->criarTipoMaterial($deputado);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 200,
            'data' => now()->format('Y-m-d'),
            'politico_senador_id' => $senadorAlheio->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['politico_senador_id']);
        $this->assertDatabaseMissing('campanha_materiais', [
            'politico_senador_id' => $senadorAlheio->id,
        ]);
    }

    public function test_rejeita_cargo_incompativel_com_o_slot(): void
    {
        $deputado = $this->criarDeputado('Deputado Cargo Errado');
        $user = $this->criarUsuario($deputado);

        // Político federal não pode ocupar o slot de senador.
        $federal = $this->criarPolitico($deputado, 'federal');
        $tipo = $this->criarTipoMaterial($deputado);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 50,
            'data' => now()->format('Y-m-d'),
            'politico_senador_id' => $federal->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['politico_senador_id']);
    }

    public function test_rejeita_lideranca_de_outro_deputado(): void
    {
        $deputado = $this->criarDeputado('Deputado Lideranca Propria');
        $outroDeputado = $this->criarDeputado('Deputado Lideranca Alheia');

        $user = $this->criarUsuario($deputado);
        $outroUser = $this->criarUsuario($outroDeputado);
        $tipo = $this->criarTipoMaterial($deputado);

        $municipio = Municipio::query()->first();
        $cargo = \App\Models\CargoLideranca::query()->first();

        $liderancaAlheia = Lideranca::create([
            'deputado_id' => $outroDeputado->id,
            'user_id' => $outroUser->id,
            'id_municipio' => $municipio->id_municipio,
            'classificacao_id' => $cargo->classificacao_id,
            'funcao_id' => $cargo->id,
            'nome' => 'Lideranca Alheia Campanha',
            'alinhamento' => 'aliado',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'lideranca_id' => $liderancaAlheia->id,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 30,
            'data' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lideranca_id']);
    }

    public function test_rejeita_tipo_material_de_outro_deputado_ou_inativo(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Proprio');
        $outroDeputado = $this->criarDeputado('Deputado Tipo Alheio');

        $user = $this->criarUsuario($deputado);
        $tipoAlheio = $this->criarTipoMaterial($outroDeputado);
        $tipoInativo = $this->criarTipoMaterial($deputado, ['ativo' => false]);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $payloadBase = [
            'id_municipio' => $municipio->id_municipio,
            'quantidade' => 10,
            'data' => now()->format('Y-m-d'),
        ];

        // Tipo de outro deputado → 422.
        $alheio = $this->postJson('/api/campanha/materiais', array_merge($payloadBase, [
            'tipo_material_id' => $tipoAlheio->id,
        ]));
        $alheio->assertStatus(422);
        $alheio->assertJsonValidationErrors(['tipo_material_id']);

        // Tipo inativo na CRIAÇÃO → 422 (não pode escolher um tipo já desativado).
        $inativo = $this->postJson('/api/campanha/materiais', array_merge($payloadBase, [
            'tipo_material_id' => $tipoInativo->id,
        ]));
        $inativo->assertStatus(422);
        $inativo->assertJsonValidationErrors(['tipo_material_id']);
    }

    public function test_permite_manter_tipo_material_inativado_apos_a_criacao(): void
    {
        $deputado = $this->criarDeputado('Deputado Tipo Mantido');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado, ['nome' => 'Sera Inativado']);
        $material = $this->criarMaterial($deputado, $user, ['tipo_material_id' => $tipo->id]);
        $municipio = Municipio::query()->first();

        // Tipo desativado DEPOIS que o material já o usava.
        $tipo->update(['ativo' => false]);

        $this->actingAs($user, 'sanctum');

        // Atualizar o material MANTENDO o mesmo tipo (agora inativo) deve
        // continuar funcionando — só a TROCA para um tipo diferente exige ativo.
        $update = $this->putJson("/api/campanha/materiais/{$material->id}", [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 999,
            'data' => now()->format('Y-m-d'),
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.quantidade', 999);

        // Trocar para outro tipo também inativo → 422.
        $outroTipoInativo = $this->criarTipoMaterial($deputado, ['ativo' => false]);
        $trocaBloqueada = $this->putJson("/api/campanha/materiais/{$material->id}", [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $outroTipoInativo->id,
            'quantidade' => 999,
            'data' => now()->format('Y-m-d'),
        ]);
        $trocaBloqueada->assertStatus(422);
        $trocaBloqueada->assertJsonValidationErrors(['tipo_material_id']);
    }

    public function test_escopo_por_deputado_no_index_e_show(): void
    {
        $deputado = $this->criarDeputado('Deputado Material Escopo A');
        $outroDeputado = $this->criarDeputado('Deputado Material Escopo B');

        $user = $this->criarUsuario($deputado);
        $outroUser = $this->criarUsuario($outroDeputado);

        $materialProprio = $this->criarMaterial($deputado, $user);
        $materialAlheio = $this->criarMaterial($outroDeputado, $outroUser);

        $this->actingAs($user, 'sanctum');

        $index = $this->getJson('/api/campanha/materiais?all=true');
        $index->assertOk();
        $ids = collect($index->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($materialProprio->id));
        $this->assertFalse($ids->contains($materialAlheio->id));

        $this->getJson("/api/campanha/materiais/{$materialAlheio->id}")->assertNotFound();
        $this->deleteJson("/api/campanha/materiais/{$materialAlheio->id}")->assertNotFound();

        $this->assertDatabaseHas('campanha_materiais', ['id' => $materialAlheio->id]);
    }

    public function test_meta_traz_contadores_por_status_sobre_o_conjunto_filtrado(): void
    {
        $deputado = $this->criarDeputado('Deputado KPIs');
        $user = $this->criarUsuario($deputado);

        $tipoSantinhos = $this->criarTipoMaterial($deputado, ['nome' => 'Santinhos']);
        $tipoAdesivos = $this->criarTipoMaterial($deputado, ['nome' => 'Adesivos']);

        // Santinhos: 2 solicitados + 1 entregue. Adesivos: 1 cancelado.
        $this->criarMaterial($deputado, $user, ['tipo_material_id' => $tipoSantinhos->id, 'status' => 'solicitado']);
        $this->criarMaterial($deputado, $user, ['tipo_material_id' => $tipoSantinhos->id, 'status' => 'solicitado']);
        $this->criarMaterial($deputado, $user, ['tipo_material_id' => $tipoSantinhos->id, 'status' => 'entregue']);
        $this->criarMaterial($deputado, $user, ['tipo_material_id' => $tipoAdesivos->id, 'status' => 'cancelado']);

        $this->actingAs($user, 'sanctum');

        // Sem filtro: contadores sobre tudo do deputado.
        $semFiltro = $this->getJson('/api/campanha/materiais?per_page=1');
        $semFiltro->assertOk();
        $semFiltro->assertJsonPath('meta.por_status.solicitado', 2);
        $semFiltro->assertJsonPath('meta.por_status.produzido', 0);
        $semFiltro->assertJsonPath('meta.por_status.entregue', 1);
        $semFiltro->assertJsonPath('meta.por_status.cancelado', 1);
        $semFiltro->assertJsonPath('meta.total_filtrado', 4);

        // Paginação continua presente no meta.
        $this->assertSame(4, $semFiltro->json('meta.total'));

        // Com filtro por tipo: contadores calculados só sobre o conjunto filtrado.
        $filtrado = $this->getJson("/api/campanha/materiais?tipo_material_id={$tipoSantinhos->id}");
        $filtrado->assertOk();
        $filtrado->assertJsonPath('meta.por_status.solicitado', 2);
        $filtrado->assertJsonPath('meta.por_status.entregue', 1);
        $filtrado->assertJsonPath('meta.por_status.cancelado', 0);
        $filtrado->assertJsonPath('meta.total_filtrado', 3);
    }

    public function test_rejeita_arte_url_com_scheme_diferente_de_http_https_e_observacao_gigante(): void
    {
        $deputado = $this->criarDeputado('Deputado Scheme Arte');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $payloadBase = [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 100,
            'data' => now()->addDays(3)->format('Y-m-d'),
        ];

        // Scheme não http/https → 422.
        $scheme = $this->postJson('/api/campanha/materiais', array_merge($payloadBase, [
            'arte_url' => 'ftp://exemplo.com/arte.pdf',
        ]));
        $scheme->assertStatus(422);
        $scheme->assertJsonValidationErrors(['arte_url']);

        // Observação acima de 5000 caracteres → 422.
        $observacao = $this->postJson('/api/campanha/materiais', array_merge($payloadBase, [
            'observacao' => str_repeat('a', 5001),
        ]));
        $observacao->assertStatus(422);
        $observacao->assertJsonValidationErrors(['observacao']);

        // Valores válidos seguem aceitos.
        $this->postJson('/api/campanha/materiais', array_merge($payloadBase, [
            'arte_url' => 'https://exemplo.com/arte.pdf',
            'observacao' => str_repeat('a', 5000),
        ]))->assertCreated();
    }

    public function test_query_params_invalidos_no_index_retornam_422_e_nao_500(): void
    {
        $deputado = $this->criarDeputado('Deputado Material Query Params');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/campanha/materiais?per_page=0')->assertStatus(422);
        $this->getJson('/api/campanha/materiais?per_page=-1')->assertStatus(422);
        $this->getJson('/api/campanha/materiais?search[]=x')->assertStatus(422);
        $this->getJson('/api/campanha/materiais?status[]=entregue')->assertStatus(422);
        $this->getJson('/api/campanha/materiais?municipio=abc')->assertStatus(422);
        $this->getJson('/api/campanha/materiais?tipo_material_id=abc')->assertStatus(422);

        // Valores válidos (ou vazios, que caem no padrão) seguem funcionando.
        $this->getJson('/api/campanha/materiais?per_page=1&search=abc')->assertOk();
        $this->getJson('/api/campanha/materiais?per_page=')->assertOk();
    }

    public function test_validacoes_obrigatorias_do_material(): void
    {
        $deputado = $this->criarDeputado('Deputado Material Validacao');
        $user = $this->criarUsuario($deputado);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/campanha/materiais', [
            'quantidade' => 0,
            'status' => 'perdido',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id_municipio', 'tipo_material_id', 'quantidade', 'status', 'data']);
    }

    public function test_cria_material_com_variante_valida(): void
    {
        $deputado = $this->criarDeputado('Deputado Material Variante');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado, [
            'nome' => 'Camisetas',
            'categoria' => 'vestuario',
            'unidade' => 'peça',
            'variantes' => ['P', 'M', 'G'],
        ]);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $store = $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'variante' => 'M',
            'quantidade' => 300,
            'data' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $store->assertCreated();
        $store->assertJsonPath('data.variante', 'M');
        $store->assertJsonPath('data.tipo_material.categoria', 'vestuario');
        $store->assertJsonPath('data.tipo_material.unidade', 'peça');

        $this->assertDatabaseHas('campanha_materiais', [
            'id' => $store->json('data.id'),
            'tipo_material_id' => $tipo->id,
            'variante' => 'M',
        ]);
    }

    public function test_rejeita_variante_fora_do_catalogo_do_tipo(): void
    {
        $deputado = $this->criarDeputado('Deputado Variante Fora');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado, ['variantes' => ['P', 'M', 'G']]);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'variante' => 'XG',
            'quantidade' => 50,
            'data' => now()->format('Y-m-d'),
        ])->assertStatus(422)->assertJsonValidationErrors(['variante']);
    }

    public function test_exige_variante_quando_o_tipo_tem_variantes(): void
    {
        $deputado = $this->criarDeputado('Deputado Variante Exigida');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado, ['variantes' => ['P', 'M']]);
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 50,
            'data' => now()->format('Y-m-d'),
        ])->assertStatus(422)->assertJsonValidationErrors(['variante']);
    }

    public function test_variante_ausente_e_proibida_quando_o_tipo_nao_tem_variantes(): void
    {
        $deputado = $this->criarDeputado('Deputado Sem Variante');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado); // variantes = []
        $municipio = Municipio::query()->first();

        $this->actingAs($user, 'sanctum');

        // Sem variante → aceito, snapshot null.
        $ok = $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'quantidade' => 50,
            'data' => now()->format('Y-m-d'),
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('data.variante', null);

        // Enviar variante para um tipo sem variantes → 422 (prohibited).
        $this->postJson('/api/campanha/materiais', [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'variante' => 'M',
            'quantidade' => 50,
            'data' => now()->format('Y-m-d'),
        ])->assertStatus(422)->assertJsonValidationErrors(['variante']);
    }

    public function test_editar_mantendo_tipo_aceita_variante_fora_do_catalogo_atual(): void
    {
        $deputado = $this->criarDeputado('Deputado Variante Leniente');
        $user = $this->criarUsuario($deputado);
        $tipo = $this->criarTipoMaterial($deputado, ['variantes' => ['P', 'M']]);
        $material = $this->criarMaterial($deputado, $user, [
            'tipo_material_id' => $tipo->id,
            'variante' => 'M',
        ]);
        $municipio = Municipio::query()->first();

        // O catálogo do tipo muda e "M" deixa de existir.
        $tipo->update(['variantes' => ['G']]);

        $this->actingAs($user, 'sanctum');

        // Editar MANTENDO o mesmo tipo e o snapshot antigo ("M") não quebra.
        $update = $this->putJson("/api/campanha/materiais/{$material->id}", [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'variante' => 'M',
            'quantidade' => 777,
            'data' => now()->format('Y-m-d'),
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.variante', 'M');
        $update->assertJsonPath('data.quantidade', 777);

        // Também aceita migrar para uma variante válida do catálogo atual.
        $troca = $this->putJson("/api/campanha/materiais/{$material->id}", [
            'id_municipio' => $municipio->id_municipio,
            'tipo_material_id' => $tipo->id,
            'variante' => 'G',
            'quantidade' => 777,
            'data' => now()->format('Y-m-d'),
        ]);
        $troca->assertOk();
        $troca->assertJsonPath('data.variante', 'G');
    }
}
