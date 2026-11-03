<?php

namespace Tests\Feature;

use App\Models\Reclamacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Canal "Reclame aqui" (ReclamacaoController). */
class ReclameAquiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pessoa_envia_e_ve_a_resposta_da_administracao(): void
    {
        $u = User::factory()->create();
        $admin = User::factory()->create(['email' => 'xexplosion20@gmail.com']);

        $this->actingAs($u)->post('/reclame-aqui', [
            'tipo' => 'problema', 'assunto' => 'PDF cortado', 'mensagem' => 'O PDF do documento saiu cortado no fim.',
            'pagina' => url('/areas/carteira'),
        ])->assertRedirect('/reclame-aqui');
        $r = Reclamacao::firstOrFail();
        $this->assertSame('aberta', $r->status);
        $this->assertSame(url('/areas/carteira'), $r->pagina);

        // menu do admin mostra 1 aberta; a página de admin lista a mensagem
        $this->actingAs($admin)->get('/admin/reclamacoes')->assertOk()->assertSee('PDF cortado')->assertSee($u->email);
        $this->actingAs($admin)->post("/admin/reclamacoes/{$r->id}", ['resposta' => 'Corrigido, obrigado!', 'status' => 'resolvida'])->assertRedirect();

        // a pessoa vê o aviso "1 nova" no menu e a resposta na página; depois de ver, o aviso some
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('1 nova');
        $this->actingAs($u)->get('/reclame-aqui')->assertOk()->assertSee('Corrigido, obrigado!')->assertSee('Resolvida');
        $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee('1 nova');
    }

    public function test_quem_nao_e_admin_nao_ve_nem_responde(): void
    {
        $u = User::factory()->create();
        $outro = User::factory()->create();
        $r = Reclamacao::create(['user_id' => $outro->id, 'tipo' => 'sugestao', 'assunto' => 'Ideia', 'mensagem' => 'Uma ideia qualquer para o site.']);

        $this->actingAs($u)->get('/admin/reclamacoes')->assertNotFound();
        $this->actingAs($u)->post("/admin/reclamacoes/{$r->id}", ['resposta' => 'x', 'status' => 'resolvida'])->assertNotFound();
        // e não vê mensagens de outras pessoas
        $this->actingAs($u)->get('/reclame-aqui')->assertOk()->assertDontSee('Uma ideia qualquer');
        $this->actingAs($u)->get('/painel')->assertDontSee('Reclamações (admin)');
    }

    public function test_sem_a_tabela_mostra_aviso_em_vez_de_erro(): void
    {
        \Illuminate\Support\Facades\Schema::drop('reclamacoes');
        $u = User::factory()->create();
        $admin = User::factory()->create(['email' => 'xexplosion20@gmail.com']);

        $this->actingAs($u)->get('/reclame-aqui')->assertOk()->assertSee('O canal está sendo ativado')->assertDontSee('Para a administração');
        $this->actingAs($admin)->get('/admin/reclamacoes')->assertOk()->assertSee('Para a administração');
        $this->actingAs($u)->post('/reclame-aqui', ['tipo' => 'elogio', 'assunto' => 'Oi', 'mensagem' => 'Mensagem de teste aqui.'])->assertRedirect('/reclame-aqui');
        $this->actingAs($u)->get('/painel')->assertOk();
    }

    public function test_validacao_limite_e_pagina_de_outro_site(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/reclame-aqui', ['tipo' => 'xyz', 'assunto' => 'a', 'mensagem' => 'curta'])->assertSessionHasErrors(['tipo', 'assunto', 'mensagem']);

        $this->actingAs($u)->post('/reclame-aqui', ['tipo' => 'elogio', 'assunto' => 'Muito bom', 'mensagem' => 'Gostei demais do estúdio.', 'pagina' => 'https://evil.com/x']);
        $this->assertNull(Reclamacao::latest('id')->first()->pagina);

        for ($i = 0; $i < 6; $i++) {
            $resp = $this->actingAs($u)->post('/reclame-aqui', ['tipo' => 'elogio', 'assunto' => 'Teste ' . $i, 'mensagem' => 'Mensagem repetida ' . $i]);
        }
        $resp->assertSessionHasErrors('mensagem');
        $this->assertSame(5, Reclamacao::count());
    }
}
