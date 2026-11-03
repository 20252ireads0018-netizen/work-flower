<?php

namespace Tests\Feature;

use App\Models\CorpoDado;
use App\Models\PixPagamento;
use App\Models\User;
use App\Support\Pix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Revisão de segurança e desempenho de 03/11/2026: o que mudou continua funcionando como antes. */
class BlindagemTest extends TestCase
{
    use RefreshDatabase;

    private function conta(string $plano = 'gratuito'): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update([
            'plano_slug' => $plano,
            'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth(),
        ]);

        return $u->fresh();
    }

    public function test_convidado_recebe_aviso_do_que_o_dono_mudou_e_perde_se_o_plano_cair(): void
    {
        $dono = $this->conta('basico');
        $membro = $this->conta();
        $this->actingAs($dono)->postJson('/compartilhar/mente', ['email' => $membro->email])->assertOk();
        $this->actingAs($dono)->postJson('/compartilhar/diverso', ['email' => $membro->email])->assertOk();
        $this->actingAs($dono)->putJson('/mente/dados/estado', ['valor' => '{"a":1}'])->assertOk();
        $this->actingAs($dono)->putJson('/diverso/dados/estado', ['valor' => '{"b":1}'])->assertOk();

        $r = $this->actingAs($membro)->getJson('/sincronia')->assertOk()->assertJsonCount(2, 'novidades');
        $this->assertEqualsCanonicalizing(["mente:{$dono->id}", "diverso:{$dono->id}"], collect($r->json('novidades'))->pluck('chave')->all());
        $r->assertJsonPath('novidades.0.dono_nome', $dono->name)->assertJsonPath('novidades.0.meu', false);

        DB::table('users')->where('id', $dono->id)->update(['plano_slug' => 'gratuito', 'plano_expira_em' => null]);
        $this->actingAs($membro)->getJson('/sincronia')->assertOk()->assertJsonCount(0, 'novidades');
    }

    public function test_pagina_do_corpo_so_leva_estado_e_imagens(): void
    {
        $u = $this->conta();
        CorpoDado::insert([
            ['user_id' => $u->id, 'chave' => 'estado', 'valor' => '{"corpo":1}'],
            ['user_id' => $u->id, 'chave' => 'img.abc', 'valor' => '"data:image/png;base64,AAA"'],
            ['user_id' => $u->id, 'chave' => 'mente.estado', 'valor' => '{"SEGREDO_MENTE":1}'],
        ]);

        $this->actingAs($u)->get('/areas/corpo')->assertOk()
            ->assertSee('img.abc', false)->assertSee(substr(sha1('{"corpo":1}'), 0, 16), false)
            ->assertDontSee('SEGREDO_MENTE', false);
    }

    public function test_abrir_outro_plano_nao_cancela_pix_que_a_pessoa_disse_que_pagou(): void
    {
        DB::table('plans')->insert([
            ['name' => 'Gratuito', 'slug' => 'gratuito', 'price' => 0, 'active' => 1, 'sort' => 1],
            ['name' => 'Básico', 'slug' => 'basico', 'price' => 19.90, 'active' => 1, 'sort' => 2],
        ]);
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'loja@exemplo.com', 'nome' => 'Joao', 'cidade' => 'Sao Paulo']);
        $u = $this->conta();
        $pago = PixPagamento::create(['user_id' => $u->id, 'plano_slug' => 'basico', 'valor' => 15.00, 'txid' => 'WFPAGO000000001', 'status' => 'informado']);
        $velho = PixPagamento::create(['user_id' => $u->id, 'plano_slug' => 'basico', 'valor' => 12.00, 'txid' => 'WFVELHO00000001', 'status' => 'aguardando']);

        $this->actingAs($u)->get('/assinar/basico')->assertRedirect();

        $this->assertSame('informado', $pago->fresh()->status);
        $this->assertSame('cancelado', $velho->fresh()->status);
        $this->assertSame(1, PixPagamento::where('user_id', $u->id)->where('status', 'aguardando')->count());
    }

    public function test_importar_o_corpo_nao_apaga_nem_troca_o_ambiente_e_o_contador(): void
    {
        $u = $this->conta('premium');
        CorpoDado::insert([
            ['user_id' => $u->id, 'chave' => 'amb.corpo', 'valor' => '{"meu":1}'],
            ['user_id' => $u->id, 'chave' => 'downloads_feitos', 'valor' => '7'],
        ]);

        // exportar não leva o que é da conta
        $this->actingAs($u)->getJson('/workflows/exportar?ids[]=corpo')->assertOk()
            ->assertJsonMissing(['chave' => 'amb.corpo'])->assertJsonMissing(['chave' => 'downloads_feitos']);

        // arquivo antigo que ainda traz essas chaves: importa o resto e ignora elas
        $this->actingAs($u)->postJson('/workflows/importar', [
            'formato' => 'work-flower.workflows', 'versao' => 1,
            'workflows' => [['area' => 'corpo', 'dados' => [
                ['chave' => 'estado', 'valor' => '{"x":1}'],
                ['chave' => 'amb.corpo', 'valor' => '{"de_fora":1}'],
                ['chave' => 'downloads_feitos', 'valor' => '0'],
            ]]],
        ])->assertOk();

        $this->assertSame('{"x":1}', CorpoDado::where(['user_id' => $u->id, 'chave' => 'estado'])->value('valor'));
        $this->assertSame('{"meu":1}', CorpoDado::where(['user_id' => $u->id, 'chave' => 'amb.corpo'])->value('valor'));
        $this->assertSame('7', CorpoDado::where(['user_id' => $u->id, 'chave' => 'downloads_feitos'])->value('valor'));
    }

    public function test_chave_longa_da_mente_e_404_e_nao_erro(): void
    {
        $this->actingAs($this->conta())->putJson('/mente/dados/' . str_repeat('a', 300), ['valor' => 'x'])->assertNotFound();
    }

    public function test_links_dos_cartoes_so_abrem_endereco_seguro(): void
    {
        $vivo = file_get_contents(resource_path('views/areas/partials/bloco-vivo.blade.php'));
        $this->assertStringContainsString('/^\/areas\/[a-z]+$/.test(b.dados.pagina', $vivo);
        $carteira = file_get_contents(public_path('js/carteira.js'));
        $this->assertStringContainsString("/^https?:\/\//i.test(String(a.url", $carteira);
        $this->assertStringContainsString("type: pdf ? 'application/pdf' : 'application/octet-stream'", $carteira);
    }
}
