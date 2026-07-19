<?php

namespace Tests\Feature;

use App\Models\Deputado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Garante contador de rate limit zerado por teste (o limiter usa o cache).
        Cache::flush();
    }

    private function criarDeputado(): Deputado
    {
        return Deputado::create([
            'titulo_eleitoral' => fake()->unique()->numerify('##########'),
            'nome' => 'Deputado Login '.uniqid(),
            'partido' => 'AAA',
            'cargo' => 'deputado estadual',
            'ativo' => true,
        ]);
    }

    public function test_excesso_de_tentativas_de_login_retorna_429(): void
    {
        $deputado = $this->criarDeputado();
        $email = fake()->unique()->safeEmail();

        User::factory()->create([
            'email' => $email,
            'password' => Hash::make('Senha@Forte1'),
            'deputado_id' => $deputado->id,
            'ativo' => true,
        ]);

        // 5 tentativas (com senha errada) são permitidas e retornam 401.
        for ($i = 0; $i < 5; $i++) {
            $resposta = $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'senha-errada',
            ]);

            $resposta->assertStatus(401);
        }

        // 6ª tentativa dentro da mesma janela é bloqueada pelo throttle.
        $bloqueada = $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'senha-errada',
        ]);

        $bloqueada->assertStatus(429);
    }

    public function test_login_valido_dentro_do_limite_funciona(): void
    {
        $deputado = $this->criarDeputado();
        $email = fake()->unique()->safeEmail();

        User::factory()->create([
            'email' => $email,
            'password' => Hash::make('Senha@Forte1'),
            'deputado_id' => $deputado->id,
            'ativo' => true,
        ]);

        $resposta = $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'Senha@Forte1',
        ]);

        $resposta->assertOk();
        $resposta->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles']]);
    }
}
