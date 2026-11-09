<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\PortfolioMensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Negócios › Portfólio: editor, prévia ao vivo, página pública /p/{slug}, formulário de contato e imagens. */
class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // estes testes mandam <script> e javascript: de propósito para conferir a limpeza do próprio portfólio
        // (segunda camada); no site o filtro de ataques recusa antes (ver test_filtro_de_ataques_barra_script_no_portfolio)
        $this->withoutMiddleware(\App\Http\Middleware\FiltroAtaques::class);
    }

    public function test_filtro_de_ataques_barra_script_no_portfolio(): void
    {
        $this->withMiddleware(\App\Http\Middleware\FiltroAtaques::class);
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana-x', 'publicado' => false, 'dados' => $this->dados()])->assertStatus(403);
        $this->assertSame(0, Portfolio::count());
    }

    private function dados(array $extra = []): array
    {
        return array_replace_recursive([
            'perfil' => ['nome' => 'Ana Souza', 'titulo' => 'Designer de produto', 'resumo' => 'Faço apps fáceis de usar.', 'email' => 'ana@exemplo.com',
                'botao_texto' => 'Me contrate', 'links' => [['rede' => 'github', 'url' => '@anasouza'], ['rede' => 'whatsapp', 'url' => '81 99999-1234'], ['rede' => 'site', 'url' => 'javascript:alert(1)']]],
            'tema'   => ['preset' => 'escuro', 'cor' => '#FF0066', 'fonte_titulo' => 'playfair', 'layout' => 'lateral', 'fundo_tipo' => 'gradiente', 'cantos' => 'invalido'],
            'seo'    => ['titulo' => 'Ana · Designer', 'descricao' => 'Portfólio da Ana', 'indexar' => true],
            'secoes' => [
                ['id' => 'aaa', 'tipo' => 'sobre', 'titulo' => 'Sobre', 'texto' => "Olá!\nSou a Ana <script>x</script>"],
                ['id' => 'bbb', 'tipo' => 'projetos', 'titulo' => 'Projetos', 'estilo' => 'mosaico', 'itens' => [
                    ['titulo' => 'App de receitas', 'texto' => 'Um app', 'link' => 'receitas.app', 'tags' => 'Figma, React'],
                    ['titulo' => '', 'texto' => ''], // vazio: sai
                ]],
                ['id' => 'ccc', 'tipo' => 'habilidades', 'titulo' => 'Habilidades', 'estilo' => 'barras', 'itens' => [['titulo' => 'Figma', 'nivel' => 140]]],
                ['id' => 'ddd', 'tipo' => 'depoimentos', 'titulo' => 'Escondida', 'visivel' => false, 'itens' => [['texto' => 'Ótima!', 'titulo' => 'Bia']]],
                ['id' => 'eee', 'tipo' => 'contato', 'titulo' => 'Fale comigo', 'formulario' => true],
                ['tipo' => 'nao-existe', 'titulo' => 'x'],
            ],
        ], $extra);
    }

    public function test_cria_personaliza_e_publica_o_portfolio(): void
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]); // plano gratuito, já sem o teste grátis: mostra a marca
        \Illuminate\Support\Facades\DB::table('users')->where('id', $u->id)->update(['plano_slug' => 'gratuito', 'plano_expira_em' => null]);
        $u = $u->fresh();
        $this->actingAs($u)->get('/negocios/portfolio')->assertOk()->assertSee('Prévia ao vivo')->assertSee('portfolio-editor.js');

        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana-souza', 'publicado' => true, 'dados' => $this->dados()])
            ->assertOk()->assertJsonPath('publicado', true)->assertJsonPath('url', url('/p/ana-souza'));

        $pf = Portfolio::where('user_id', $u->id)->firstOrFail();
        $c = $pf->conteudo();
        $this->assertSame('#ff0066', $c['tema']['cor']);
        $this->assertSame('#0b1020', $c['tema']['fundo']);          // veio do tema "escuro"
        $this->assertSame('suaves', $c['tema']['cantos']);           // opção inválida volta ao padrão
        $this->assertSame('playfair', $c['tema']['fonte_titulo']);
        $this->assertCount(5, $c['secoes']);                         // tipo desconhecido sai
        $this->assertSame(['aaa', 'bbb', 'ccc', 'ddd', 'eee'], array_column($c['secoes'], 'id'));
        $this->assertCount(1, $c['secoes'][1]['itens']);             // item vazio sai
        $this->assertSame('https://receitas.app', $c['secoes'][1]['itens'][0]['link']);
        $this->assertSame(100, $c['secoes'][2]['itens'][0]['nivel']);
        $this->assertSame(['https://github.com/anasouza', 'https://wa.me/5581999991234'], array_column($c['perfil']['links'], 'url')); // javascript: sai

        auth()->logout();
        $r = $this->get('/p/ana-souza')->assertOk()
            ->assertSee('<title>Ana · Designer</title>', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Person","name":"Ana Souza"', false)
            ->assertSee('App de receitas')->assertSee('Figma')
            ->assertSee('Sou a Ana &lt;script&gt;x&lt;/script&gt;', false)->assertDontSee('<script>x</script>', false)
            ->assertDontSee('Escondida')                              // seção escondida
            ->assertDontSee('javascript:alert', false)
            ->assertSee('pf-l-lateral', false)
            ->assertSee('fonts.googleapis.com/css2?family=Playfair+Display', false)
            ->assertSee(route('portfolio.contato', 'ana-souza'), false)
            ->assertSee('Portfólio feito com');
        $this->assertSame(1, $pf->fresh()->visitas);
    }

    public function test_reordenar_secoes_muda_a_ordem_na_pagina(): void
    {
        $u = User::factory()->create();
        $d = $this->dados();
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $d])->assertOk();
        $html = $this->get('/p/ana')->getContent();
        $this->assertLessThan(strpos($html, 'id="s-ccc"'), strpos($html, 'id="s-bbb"'));

        $d['secoes'] = [$d['secoes'][2], $d['secoes'][0], $d['secoes'][1], $d['secoes'][4]];
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $d])->assertOk();
        $html = $this->get('/p/ana')->getContent();
        $this->assertLessThan(strpos($html, 'id="s-bbb"'), strpos($html, 'id="s-ccc"'));
        $this->assertSame(['ccc', 'aaa', 'bbb', 'eee'], array_column(Portfolio::first()->conteudo()['secoes'], 'id'));
    }

    public function test_previa_ao_vivo_mostra_o_que_ainda_nao_foi_salvo(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/negocios/portfolio/previa', ['dados' => $this->dados(['perfil' => ['nome' => 'Nome Ainda Não Salvo']])])
            ->assertOk()->assertSee('Nome Ainda Não Salvo')->assertSee('noindex, nofollow')
            ->assertSee('Na prévia o formulário não envia');
        $this->assertSame(0, Portfolio::count());
        $this->post('/negocios/portfolio/previa', ['dados' => []])->assertSessionHasErrors('dados');
        auth()->logout();
        $this->post('/negocios/portfolio/previa', ['dados' => $this->dados()])->assertRedirect('/login');
    }

    public function test_rascunho_so_o_dono_ve_e_endereco_e_validado(): void
    {
        $u = User::factory()->create();
        $outro = User::factory()->create();
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => false, 'dados' => $this->dados()])->assertOk();
        $this->actingAs($u)->get('/p/ana')->assertOk()->assertSee('não está publicado')->assertSee('noindex, nofollow');
        $this->assertSame(0, Portfolio::first()->visitas);
        $this->actingAs($outro)->get('/p/ana')->assertNotFound();

        $this->actingAs($outro)->postJson('/negocios/portfolio', ['slug' => 'ana', 'dados' => $this->dados()])->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->actingAs($outro)->postJson('/negocios/portfolio', ['slug' => 'admin', 'dados' => $this->dados()])->assertStatus(422);
        $this->actingAs($outro)->postJson('/negocios/portfolio', ['slug' => 'Com Espaco', 'dados' => $this->dados()])->assertStatus(422);
        $this->get('/p/nao-existe')->assertNotFound();
    }

    public function test_formulario_de_contato_guarda_a_mensagem_para_o_dono(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $this->dados()])->assertOk();
        auth()->logout();

        $this->post('/p/ana/contato', ['nome' => 'Carlos', 'contato' => 'carlos@empresa.com', 'assunto' => 'Projeto', 'mensagem' => 'Quero um app novo.'])
            ->assertRedirect(url('/p/ana') . '#contato')->assertSessionHas('pf_ok');
        $this->post('/p/ana/contato', ['nome' => 'Robô', 'contato' => 'x@y.com', 'mensagem' => 'spam spam', 'site' => 'http://spam'])->assertRedirect();
        $this->post('/p/ana/contato', ['nome' => 'Sem msg', 'contato' => 'x@y.com'])->assertSessionHasErrors('mensagem');
        $this->assertSame(1, PortfolioMensagem::count());

        $m = PortfolioMensagem::first();
        $this->actingAs($u)->get('/negocios/portfolio')->assertSee('Quero um app novo.');
        $this->actingAs(User::factory()->create())->postJson("/negocios/portfolio/mensagens/{$m->id}/lida", ['lida' => true])->assertNotFound();
        $this->actingAs($u)->postJson("/negocios/portfolio/mensagens/{$m->id}/lida", ['lida' => true])->assertOk();
        $this->assertTrue($m->fresh()->lida);
        $this->actingAs($u)->deleteJson("/negocios/portfolio/mensagens/{$m->id}")->assertOk();
        $this->assertSame(0, PortfolioMensagem::count());

        // formulário desligado: não aceita
        $d = $this->dados();
        $d['secoes'][4]['formulario'] = false;
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $d])->assertOk();
        auth()->logout();
        $this->post('/p/ana/contato', ['nome' => 'Carlos', 'contato' => 'c@e.com', 'mensagem' => 'Oi de novo'])->assertNotFound();
    }

    public function test_limite_de_mensagens_por_visitante(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $this->dados()])->assertOk();
        auth()->logout();
        $pf = Portfolio::first();
        $ip = hash('sha256', '127.0.0.1|' . config('app.key'));
        for ($i = 0; $i < 5; $i++) $pf->mensagens()->create(['nome' => 'a', 'contato' => 'a@a.com', 'mensagem' => 'oi oi', 'ip_hash' => $ip]);
        $this->post('/p/ana/contato', ['nome' => 'Carlos', 'contato' => 'c@e.com', 'mensagem' => 'Mais uma'])->assertSessionHasErrors('mensagem');
        $this->assertSame(5, PortfolioMensagem::count());
    }

    public function test_imagens_so_do_dono_e_servidas_pela_pagina_publica(): void
    {
        $u = User::factory()->create();
        $outro = User::factory()->create();
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
        $enviar = function (User $quem) use ($gif) {
            $t = $this->actingAs($quem)->postJson('/midia', ['tipo' => 'image/gif', 'tamanho' => strlen($gif)])->json('token');
            $this->actingAs($quem)->putJson("/midia/{$t}/0", ['dados' => base64_encode($gif)])->assertOk();
            $this->actingAs($quem)->postJson("/midia/{$t}/fim")->assertOk();
            return $t;
        };
        $meu = $enviar($u);
        $alheio = $enviar($outro);

        $d = $this->dados(['perfil' => ['foto' => $meu, 'capa' => $alheio], 'tema' => ['fundo_tipo' => 'imagem', 'fundo_img' => 'https://exemplo.com/fundo.gif'], 'seo' => ['imagem' => 'http://inseguro.com/a.png']]);
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $d])->assertOk();
        $c = Portfolio::first()->conteudo();
        $this->assertSame($meu, $c['perfil']['foto']);
        $this->assertNull($c['perfil']['capa']);                  // token de outra conta não entra
        $this->assertSame('https://exemplo.com/fundo.gif', $c['tema']['fundo_img']);
        $this->assertNull($c['seo']['imagem']);                   // só https

        auth()->logout();
        $this->get('/p/ana')->assertOk()->assertSee(route('portfolio.midia', ['ana', $meu]), false)->assertSee("background-image:url('https://exemplo.com/fundo.gif')", false);
        $this->get("/p/ana/m/{$meu}")->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get("/p/ana/m/{$alheio}")->assertNotFound();
        $this->get('/p/ana/m/' . str_repeat('a', 32))->assertNotFound();
    }

    public function test_excluir_o_portfolio(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/negocios/portfolio', ['slug' => 'ana', 'publicado' => true, 'dados' => $this->dados()])->assertOk();
        $this->actingAs($u)->delete('/negocios/portfolio', ['confirmar' => 'nao'])->assertSessionHasErrors('confirmar');
        $this->actingAs($u)->delete('/negocios/portfolio', ['confirmar' => 'EXCLUIR'])->assertRedirect(route('portfolio.editor'));
        $this->assertSame(0, Portfolio::count());
        $this->get('/p/ana')->assertNotFound();
    }

    public function test_todos_os_temas_layouts_e_estilos_renderizam(): void
    {
        $u = User::factory()->create();
        $secoes = [];
        foreach (Portfolio::ESTILOS as $tipo => $estilos) {
            foreach ($estilos as $e) {
                $secoes[] = ['tipo' => $tipo, 'titulo' => "$tipo $e", 'estilo' => $e, 'texto' => 'Texto', 'formulario' => true, 'fundo' => 'invertido',
                    'itens' => [['titulo' => 'Item', 'subtitulo' => 'Sub', 'texto' => 'Algo', 'img' => 'https://exemplo.com/a.png', 'link' => 'https://exemplo.com', 'tags' => 'a, b', 'nivel' => 50]]];
            }
        }
        $layouts = Portfolio::OPCOES['layout'];
        $cabecalhos = Portfolio::OPCOES['cabecalho'];
        $i = 0;
        foreach (array_keys(Portfolio::TEMAS) as $tema) {
            $d = ['perfil' => ['nome' => 'Ana', 'foto' => 'https://exemplo.com/f.png', 'capa' => 'https://exemplo.com/c.png'],
                'tema' => ['preset' => $tema, 'layout' => $layouts[$i % count($layouts)], 'cabecalho' => $cabecalhos[$i % count($cabecalhos)],
                    'titulos' => Portfolio::OPCOES['titulos'][$i % 4], 'padrao' => Portfolio::OPCOES['padrao'][$i % 4], 'modo_escuro' => true, 'vidro' => $i % 2 === 0],
                'secoes' => array_slice($secoes, 0, Portfolio::MAX_SECOES)];
            $this->actingAs($u)->post('/negocios/portfolio/previa', ['dados' => $d])->assertOk()->assertSee('pf-l-' . $d['tema']['layout'], false);
            $i++;
        }
        $this->actingAs($u)->post('/negocios/portfolio/previa', ['dados' => ['secoes' => array_slice($secoes, Portfolio::MAX_SECOES)]])->assertOk();
    }
}
