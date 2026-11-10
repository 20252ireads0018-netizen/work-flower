<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\User;
use App\Models\VagaOferta;
use App\Models\VagaResposta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vagas em cartões (/vagas/cartoes): deslizar para candidatar (com o portfólio) ou descartar, publicar e acompanhar. */
class VagasCartoesTest extends TestCase
{
    use RefreshDatabase;

    private function vaga(?User $dono, array $extra = []): VagaOferta
    {
        $v = new VagaOferta($extra + [
            'titulo' => 'Auxiliar administrativo', 'empresa' => 'Padaria Boa', 'cidade' => 'Recife - PE', 'modalidade' => 'presencial',
            'regime' => 'CLT', 'salario' => 'R$ 2.000', 'descricao' => 'Atendimento e organização do estoque da loja.', 'requisitos' => "Ensino médio\nPontualidade",
        ]);
        $v->user_id = $dono?->id;
        $v->save();

        return $v;
    }

    private function portfolio(User $u, bool $publicado = true): Portfolio
    {
        $p = new Portfolio(['slug' => 'ana-souza', 'publicado' => $publicado, 'dados' => Portfolio::inicial($u)]);
        $p->user_id = $u->id;
        $p->save();

        return $p;
    }

    public function test_pagina_exige_login_e_mostra_a_pilha_sem_as_vagas_proprias(): void
    {
        $this->get('/vagas/cartoes')->assertRedirect();

        $eu = User::factory()->create();
        $outra = User::factory()->create();
        $this->vaga($outra, ['titulo' => 'Vaga de outra empresa']);
        $this->vaga($eu, ['titulo' => 'Minha própria vaga']);

        $titulos = $this->actingAs($eu)->get('/vagas/cartoes')->assertOk()->viewData('fila')->pluck('titulo')->all();
        $this->assertContains('Vaga de outra empresa', $titulos);
        $this->assertContains('Assistente de organização (home-office)', $titulos); // vaga da plataforma criada pela migração
        $this->assertNotContains('Minha própria vaga', $titulos);
    }

    public function test_candidatar_exige_portfolio_publicado_e_leva_o_portfolio(): void
    {
        $eu = User::factory()->create();
        $empresa = User::factory()->create();
        $v = $this->vaga($empresa);

        $this->actingAs($eu)->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'candidatou'])
            ->assertStatus(422)->assertJsonPath('portfolio', false);
        $this->portfolio($eu, false);
        $this->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'candidatou'])->assertStatus(422);

        Portfolio::where('user_id', $eu->id)->update(['publicado' => true]);
        $this->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'candidatou', 'mensagem' => 'Posso começar já.'])
            ->assertOk()->assertJsonPath('candidatura.vaga.id', $v->id);

        $r = VagaResposta::firstOrFail();
        $this->assertSame('ana-souza', $r->portfolio_slug);
        $this->assertSame('Posso começar já.', $r->mensagem);

        // some da pilha; aparece em "Minhas candidaturas"
        $pagina = $this->get('/vagas/cartoes')->assertOk();
        $this->assertCount(1, $pagina->viewData('minhas'));
        $this->assertFalse($pagina->viewData('fila')->contains('id', $v->id));

        // a empresa vê o candidato com o portfólio e muda o status
        $pub = $this->actingAs($empresa)->get('/vagas/cartoes')->assertOk()->viewData('publicadas');
        $this->assertStringEndsWith('/p/ana-souza', $pub[0]['candidatos'][0]['portfolio']);
        $this->postJson("/vagas/cartoes/respostas/{$r->id}/status", ['status' => 'entrevista'])->assertOk();
        $this->assertSame('entrevista', $r->fresh()->status);

        // depois que a empresa andou, quem se candidatou não retira mais
        $this->actingAs($eu)->postJson("/vagas/cartoes/{$v->id}/desfazer")->assertStatus(422);
        // e outra conta não muda o status
        $this->postJson("/vagas/cartoes/respostas/{$r->id}/status", ['status' => 'contratada'])->assertNotFound();
    }

    public function test_descartar_desfazer_e_rever(): void
    {
        $eu = User::factory()->create();
        $v = $this->vaga(User::factory()->create());

        $this->actingAs($eu)->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'descartou'])->assertOk();
        $this->assertSame('descartou', VagaResposta::first()->acao);

        $this->postJson("/vagas/cartoes/{$v->id}/desfazer")->assertOk()->assertJsonPath('vaga.id', $v->id);
        $this->assertSame(0, VagaResposta::count());

        $this->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'descartou'])->assertOk();
        $this->postJson('/vagas/cartoes/rever')->assertOk()->assertJsonFragment(['id' => $v->id]);
        $this->assertSame(0, VagaResposta::count());

        $this->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'gostei'])->assertStatus(422);
    }

    public function test_publicar_encerrar_e_excluir_so_a_propria_vaga(): void
    {
        $eu = User::factory()->create();
        $this->actingAs($eu)->postJson('/vagas/cartoes/publicar', ['titulo' => 'X'])->assertStatus(422)
            ->assertJsonValidationErrors(['empresa', 'descricao']);

        $j = $this->postJson('/vagas/cartoes/publicar', [
            'titulo' => 'Vendedor externo', 'empresa' => 'Loja Azul', 'modalidade' => 'hibrido', 'regime' => 'CLT',
            'descricao' => 'Visitar clientes da região e fechar pedidos.', 'requisitos' => "CNH B\nExperiência com vendas", 'link' => 'https://exemplo.com/vaga',
        ])->assertOk()->json('vaga');
        $v = VagaOferta::findOrFail($j['id']);
        $this->assertSame($eu->id, $v->user_id);
        $this->assertSame(['CNH B', 'Experiência com vendas'], $j['requisitos']);

        // não dá para se candidatar à própria vaga
        $this->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'descartou'])->assertNotFound();

        $outra = User::factory()->create();
        $this->actingAs($outra)->postJson("/vagas/cartoes/{$v->id}/alternar")->assertNotFound();
        $this->deleteJson("/vagas/cartoes/{$v->id}")->assertNotFound();

        $this->actingAs($eu)->postJson("/vagas/cartoes/{$v->id}/alternar")->assertOk()->assertJsonPath('vaga.ativa', false);
        // encerrada: não aceita respostas
        $this->actingAs($outra)->postJson("/vagas/cartoes/{$v->id}/responder", ['acao' => 'descartou'])->assertNotFound();

        $this->actingAs($eu)->deleteJson("/vagas/cartoes/{$v->id}")->assertOk();
        $this->assertNull(VagaOferta::find($v->id));
    }

    public function test_pagina_publica_de_vagas_aponta_para_os_cartoes(): void
    {
        $this->get('/vagas')->assertOk()->assertSee('/vagas/cartoes');
    }
}
