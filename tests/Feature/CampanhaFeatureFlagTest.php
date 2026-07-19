<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * As rotas /api/campanha/* só são registradas quando config('campanha.ativo')
 * é true no boot da aplicação. Como o registro acontece na criação do app,
 * a flag é desligada via variável de ambiente ANTES de parent::setUp()
 * (que cria a aplicação), e restaurada no tearDown para não afetar os
 * demais testes (phpunit.xml define CAMPANHA_ATIVA=true).
 */
class CampanhaFeatureFlagTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('CAMPANHA_ATIVA=false');
        $_ENV['CAMPANHA_ATIVA'] = 'false';
        $_SERVER['CAMPANHA_ATIVA'] = 'false';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('CAMPANHA_ATIVA=true');
        $_ENV['CAMPANHA_ATIVA'] = 'true';
        $_SERVER['CAMPANHA_ATIVA'] = 'true';
    }

    public function test_rotas_de_campanha_nao_existem_com_flag_desligada(): void
    {
        $this->assertFalse(config('campanha.ativo'));

        $this->getJson('/api/campanha/politicos')->assertNotFound();
        $this->getJson('/api/campanha/materiais')->assertNotFound();
        $this->getJson('/api/campanha/tipos-material')->assertNotFound();
        $this->postJson('/api/campanha/politicos', [])->assertNotFound();
        $this->postJson('/api/campanha/materiais', [])->assertNotFound();
        $this->postJson('/api/campanha/tipos-material', [])->assertNotFound();
    }
}
