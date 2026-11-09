<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Mini-Laravel da IDE (public/js/ide-mini-laravel.txt): é o PHP que o php-wasm roda no navegador para abrir um projeto
 * Laravel do cartão de código. Aqui ele roda num processo PHP à parte (as classes dele têm os nomes do Illuminate e não
 * podem entrar no processo do teste), com o projeto de tests/ide/projeto e as views já convertidas de Blade.
 */
class IdeMiniLaravelTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = tempnam(sys_get_temp_dir(), 'wfml') . '.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->db);
        parent::tearDown();
    }

    /** Uma requisição ao app de exemplo; devolve a resposta do núcleo (status, corpo, sessão, logs…). */
    private function pedir(array $req): array
    {
        $arq = tempnam(sys_get_temp_dir(), 'wfreq');
        file_put_contents($arq, json_encode($req + ['db' => $this->db]));
        $p = new Process([PHP_BINARY, base_path('tests/ide/rodar-mini-laravel.php'), base_path('tests/ide/projeto'), $arq]);
        $p->setTimeout(60)->run();
        @unlink($arq);
        $r = json_decode($p->getOutput(), true);
        $this->assertIsArray($r, 'Saída do núcleo: ' . $p->getOutput() . $p->getErrorOutput());

        return $r;
    }

    public function test_o_nucleo_nao_tem_erro_de_sintaxe_e_nao_e_um_arquivo_php(): void
    {
        $arq = public_path('js/ide-mini-laravel.txt');
        $this->assertFileExists($arq);
        $this->assertStringNotContainsString('.php', basename($arq), 'no servidor o núcleo precisa ser só texto, nunca executado');
        $p = new Process([PHP_BINARY, '-l', $arq]);
        $p->run();
        $this->assertSame(0, $p->getExitCode(), $p->getOutput());
    }

    public function test_primeira_requisicao_cria_o_banco_e_renderiza_a_lista(): void
    {
        $r = $this->pedir(['metodo' => 'GET', 'url' => '/posts']);

        $this->assertSame(200, $r['status']);
        $this->assertSame('posts.index', $r['rota']);
        $this->assertStringContainsString('Posts (3)', $r['corpo']);
        $this->assertStringContainsString('href="http://localhost/posts/1"', $r['corpo']);
        $this->assertStringContainsString('Migrations rodadas', json_encode($r['logs']));
        $this->assertSame('http://localhost/posts', $r['sessao']['_previous.url']);
    }

    public function test_validacao_volta_com_erros_em_portugues_e_old_input(): void
    {
        $this->pedir(['url' => '/posts']);
        $r = $this->pedir(['metodo' => 'POST', 'url' => '/posts', 'corpo' => 'titulo=', 'sessao' => ['_previous.url' => 'http://localhost/posts']]);

        $this->assertSame(302, $r['status']);
        $this->assertSame('http://localhost/posts', $r['local']);
        $this->assertSame(['titulo' => ['O campo titulo é obrigatório.']], $r['sessao']['errors']);

        $volta = $this->pedir(['url' => '/posts', 'sessao' => $r['sessao']]);
        $this->assertStringContainsString('<p class="erro">O campo titulo é obrigatório.</p>', $volta['corpo']);
        // o flash vale só para uma requisição
        $depois = $this->pedir(['url' => '/posts', 'sessao' => $volta['sessao']]);
        $this->assertStringNotContainsString('class="erro"', $depois['corpo']);
    }

    public function test_crud_com_model_binding_method_spoofing_e_flash(): void
    {
        $this->pedir(['url' => '/posts']);
        $criado = $this->pedir(['metodo' => 'POST', 'url' => '/posts', 'corpo' => 'titulo=Meu+post&corpo=oi']);
        $this->assertSame(302, $criado['status']);
        $this->assertSame('http://localhost/posts/4', $criado['local']);

        // como no Laravel, campo vazio chega como null (ConvertEmptyStringsToNull) e espaços nas pontas somem (TrimStrings)
        $vazio = $this->pedir(['metodo' => 'POST', 'url' => '/posts', 'corpo' => 'titulo=++Com+espacos++&corpo=']);
        $this->assertSame(302, $vazio['status']);
        $salvo = $this->pedir(['modo' => 'artisan', 'cmd' => 'tinker json_encode(App\\Models\\Post::find(5)->only(["titulo", "corpo"]))']);
        $this->assertSame("= '{\"titulo\":\"Com espacos\",\"corpo\":null}'\n", $salvo['corpo']);

        $show = $this->pedir(['url' => '/posts/4', 'sessao' => $criado['sessao']]);
        $this->assertStringContainsString('<h1>Meu post</h1>', $show['corpo']);
        $this->assertStringContainsString('Post criado!', $show['corpo']);

        $put = $this->pedir(['metodo' => 'POST', 'url' => '/posts/4', 'corpo' => '_method=PUT&titulo=Novo+titulo']);
        $this->assertSame(302, $put['status']);
        $this->assertSame('Atualizado', $put['sessao']['ok']);
        $this->assertStringContainsString('Novo titulo', $this->pedir(['url' => '/posts/4'])['corpo']);

        $del = $this->pedir(['metodo' => 'POST', 'url' => '/posts/4', 'corpo' => '_method=DELETE']);
        $this->assertSame('http://localhost/posts', $del['local']);
        $this->assertSame(404, $this->pedir(['url' => '/posts/4'])['status']);
    }

    public function test_rotas_405_parametro_opcional_grupo_e_api_json(): void
    {
        $this->pedir(['url' => '/posts']);

        $this->assertSame(405, $this->pedir(['metodo' => 'DELETE', 'url' => '/posts'])['status']);
        $this->assertSame('Olá, mundo!', $this->pedir(['url' => '/ola'])['corpo']);
        $this->assertSame('Olá, Ana!', $this->pedir(['url' => '/ola/Ana'])['corpo']);

        $painel = $this->pedir(['url' => '/admin/painel']);
        $this->assertSame('admin.painel', $painel['rota']);
        $this->assertSame(['total' => 3], json_decode($painel['corpo'], true));

        $api = $this->pedir(['url' => '/api/posts']);
        $this->assertSame('application/json', $api['tipo']);
        $this->assertCount(3, json_decode($api['corpo'], true));
    }

    public function test_erro_de_programacao_mostra_arquivo_e_linha_do_projeto(): void
    {
        $this->pedir(['url' => '/posts']);
        $r = $this->pedir(['metodo' => 'GET', 'url' => '/posts/abc/edit']);   // a rota existe, o método edit não

        $this->assertSame(500, $r['status']);
        $this->assertStringContainsString('edit', $r['erro']['msg']);
        $this->assertStringContainsString('BadMethodCallException', $r['corpo']);
    }

    public function test_artisan_route_list_tinker_e_migrate_fresh(): void
    {
        $lista = $this->pedir(['modo' => 'artisan', 'cmd' => 'route:list']);
        $this->assertStringContainsString('posts.update', $lista['corpo']);
        $this->assertStringContainsString('PUT|PATCH', $lista['corpo']);

        $this->assertSame("= 3\n", $this->pedir(['modo' => 'artisan', 'cmd' => 'tinker App\\Models\\Post::count()'])['corpo']);

        $fresh = $this->pedir(['modo' => 'artisan', 'cmd' => 'migrate:fresh --seed']);
        $this->assertStringContainsString('DatabaseSeeder', $fresh['corpo']);

        $esquema = json_decode($this->pedir(['modo' => 'artisan', 'cmd' => 'wf:esquema'])['corpo'], true);
        $this->assertSame(['comentarios', 'migrations', 'posts'], array_column($esquema, 'nome'));

        $erro = $this->pedir(['modo' => 'artisan', 'cmd' => 'tinker App\\Models\\NaoExiste::count()']);
        $this->assertSame(500, $erro['status']);
        $this->assertStringContainsString('NaoExiste', $erro['corpo']);
    }
}
