<?php

namespace Tests\Feature;

use App\Models\Deputado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const SENHA_VALIDA = 'Senha@Forte1';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'manager', 'cabinet'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }
    }

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

    private function criarUsuarioComPapel(Deputado $deputado, string $papel): User
    {
        $user = User::factory()->create(['deputado_id' => $deputado->id, 'ativo' => true]);
        $user->assignRole($papel);

        return $user;
    }

    public function test_manager_nao_pode_criar_usuario_admin(): void
    {
        $deputado = $this->criarDeputado('Deputado Escalacao');
        $manager = $this->criarUsuarioComPapel($deputado, 'manager');

        $this->actingAs($manager, 'sanctum');

        $response = $this->postJson('/api/users', [
            'name' => 'Novo Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => self::SENHA_VALIDA,
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Você não pode criar um usuário com papel superior ao seu.',
        ]);

        $this->assertDatabaseMissing('users', ['name' => 'Novo Admin']);
    }

    public function test_manager_pode_criar_usuario_cabinet(): void
    {
        $deputado = $this->criarDeputado('Deputado Manager Cria Cabinet');
        $manager = $this->criarUsuarioComPapel($deputado, 'manager');

        $this->actingAs($manager, 'sanctum');

        $email = fake()->unique()->safeEmail();

        $response = $this->postJson('/api/users', [
            'name' => 'Assessor Gabinete',
            'email' => $email,
            'password' => self::SENHA_VALIDA,
            'role' => 'cabinet',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'cabinet');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'deputado_id' => $deputado->id,
        ]);
    }

    public function test_manager_pode_criar_outro_manager(): void
    {
        $deputado = $this->criarDeputado('Deputado Manager Cria Manager');
        $manager = $this->criarUsuarioComPapel($deputado, 'manager');

        $this->actingAs($manager, 'sanctum');

        $response = $this->postJson('/api/users', [
            'name' => 'Outro Manager',
            'email' => fake()->unique()->safeEmail(),
            'password' => self::SENHA_VALIDA,
            'role' => 'manager',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'manager');
    }

    public function test_admin_pode_criar_usuario_admin(): void
    {
        $deputado = $this->criarDeputado('Deputado Admin Cria Admin');
        $admin = $this->criarUsuarioComPapel($deputado, 'admin');

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/users', [
            'name' => 'Novo Admin Valido',
            'email' => fake()->unique()->safeEmail(),
            'password' => self::SENHA_VALIDA,
            'role' => 'admin',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'admin');
    }

    public function test_nao_e_possivel_criar_super_admin_via_role(): void
    {
        $deputado = $this->criarDeputado('Deputado Tenta Super Admin');
        $admin = $this->criarUsuarioComPapel($deputado, 'admin');

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/users', [
            'name' => 'Tentativa Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => self::SENHA_VALIDA,
            'role' => 'super_admin',
        ]);

        // Rejeitado pela validação (in:admin,manager,cabinet) antes de qualquer criação.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['name' => 'Tentativa Super Admin']);
    }

    public function test_idor_admin_nao_pode_atualizar_usuario_de_outro_deputado(): void
    {
        $deputadoA = $this->criarDeputado('Deputado A IDOR');
        $deputadoB = $this->criarDeputado('Deputado B IDOR');

        $adminA = $this->criarUsuarioComPapel($deputadoA, 'admin');
        $usuarioB = $this->criarUsuarioComPapel($deputadoB, 'cabinet');

        $this->actingAs($adminA, 'sanctum');

        $response = $this->putJson("/api/users/{$usuarioB->id}", [
            'name' => 'Nome Invadido',
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseMissing('users', [
            'id' => $usuarioB->id,
            'name' => 'Nome Invadido',
        ]);
    }

    public function test_idor_admin_nao_pode_deletar_usuario_de_outro_deputado(): void
    {
        $deputadoA = $this->criarDeputado('Deputado A IDOR Delete');
        $deputadoB = $this->criarDeputado('Deputado B IDOR Delete');

        $adminA = $this->criarUsuarioComPapel($deputadoA, 'admin');
        $usuarioB = $this->criarUsuarioComPapel($deputadoB, 'cabinet');

        $this->actingAs($adminA, 'sanctum');

        $response = $this->deleteJson("/api/users/{$usuarioB->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('users', [
            'id' => $usuarioB->id,
            'deleted_at' => null,
        ]);
    }
}
