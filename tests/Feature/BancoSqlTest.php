<?php

namespace Tests\Feature;

use App\Models\DiversoDado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cartão "Banco de dados (.sql)" da área Diverso: as linhas vão em pedaços sqlz.<cartão>.<versão>.<n>
 * (gzip em base64, nunca SQL cru: o mod_security da hospedagem recusa com 406), lidos um a um quando a
 * tabela abre, e não entram na página.
 */
class BancoSqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_pedaco_vai_e_volta_em_base64(): void
    {
        $u = User::factory()->create();
        $b64 = base64_encode(gzencode(json_encode([[0, [[1, "O'Neil"]]]])));

        $this->actingAs($u)->putJson('/diverso/dados/sqlz.babc.v1.0', ['valor' => $b64])->assertOk();
        $this->actingAs($u)->getJson('/diverso/dados/sqlz.babc.v1.0')->assertOk()->assertExactJson(['valor' => $b64]);
    }

    public function test_pedaco_com_texto_cru_e_recusado(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->putJson('/diverso/dados/sqlz.babc.v1.0', ['valor' => "INSERT INTO t VALUES ('a');"])->assertStatus(422);
        $this->assertDatabaseMissing('diverso_dados', ['chave' => 'sqlz.babc.v1.0']);
    }

    public function test_so_chaves_de_pedaco_podem_ser_lidas_uma_a_uma(): void
    {
        $u = User::factory()->create();
        DiversoDado::create(['user_id' => $u->id, 'chave' => 'img.babc', 'valor' => '"data:image/png;base64,AAA"']);

        $this->actingAs($u)->get('/diverso/dados/img.babc')->assertStatus(405);
        $this->actingAs($u)->getJson('/diverso/dados/sqlz.babc.v1.9')->assertNotFound();
    }

    public function test_pedacos_nao_vao_junto_com_a_pagina(): void
    {
        $u = User::factory()->create();
        DiversoDado::insert([
            ['user_id' => $u->id, 'chave' => 'img.babc.sql', 'valor' => '"wfsql2:INDICE_LEVE"'],
            ['user_id' => $u->id, 'chave' => 'sqlz.babc.v1.0', 'valor' => 'PEDACOPESADOAAAA'],
        ]);

        $this->actingAs($u)->get('/areas/diverso')->assertOk()
            ->assertSee('INDICE_LEVE', false)
            ->assertDontSee('PEDACOPESADOAAAA', false);
    }

    public function test_apagar_tira_os_pedacos_da_versao_ou_do_cartao_inteiro(): void
    {
        $u = User::factory()->create();
        $outro = User::factory()->create();
        foreach (['sqlz.babc.v1.0', 'sqlz.babc.v1.1', 'sqlz.babc.v2.0', 'sqlz.babcd.v1.0'] as $k) {
            DiversoDado::create(['user_id' => $u->id, 'chave' => $k, 'valor' => 'QQ==']);
        }
        DiversoDado::create(['user_id' => $outro->id, 'chave' => 'sqlz.babc.v2.0', 'valor' => 'QQ==']);

        $this->actingAs($u)->deleteJson('/diverso/dados/sqlz.babc.v1')->assertOk();
        $this->assertEqualsCanonicalizing(['sqlz.babc.v2.0', 'sqlz.babcd.v1.0'], DiversoDado::where('user_id', $u->id)->pluck('chave')->all());

        $this->actingAs($u)->deleteJson('/diverso/dados/sqlz.babc')->assertOk();
        $this->assertSame(['sqlz.babcd.v1.0'], DiversoDado::where('user_id', $u->id)->pluck('chave')->all());
        $this->assertSame(1, DiversoDado::where('user_id', $outro->id)->count(), 'os dados de outra conta não mudam');
    }
}
