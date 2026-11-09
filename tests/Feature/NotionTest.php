<?php

namespace Tests\Feature;

use App\Models\NotionIntegracao;
use App\Models\User;
use App\Support\Notion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Negócios › Integrações › Notion: conexão (OAuth ou token interno), lista de páginas e conteúdo em Markdown/tabela. */
class NotionTest extends TestCase
{
    use RefreshDatabase;

    // tokens de mentira, montados em partes para não parecerem tokens de verdade no código
    private static function token(): string { return 'ntn_' . str_repeat('a1B2', 10); }
    private const PAGINA = '11111111-2222-3333-4444-555555555555';
    private const BANCO = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private const SUMIU = '99999999-9999-9999-9999-999999999999';

    private ?int $falha = null;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $rt = fn (string $t, array $a = [], ?string $href = null) => ['type' => 'text', 'plain_text' => $t, 'href' => $href, 'annotations' => $a + ['bold' => false, 'italic' => false, 'code' => false, 'strikethrough' => false]];
        $bloco = fn (string $id, string $tipo, array $d, bool $filhos = false) => ['object' => 'block', 'id' => $id, 'type' => $tipo, 'has_children' => $filhos, $tipo => $d];
        Http::fake(function (HttpRequest $r) use ($rt, $bloco) {
            $u = $r->url();
            if (! str_starts_with($u, 'https://api.notion.com/v1/')) return null;
            if ($this->falha) return Http::response(['object' => 'error', 'code' => 'unauthorized'], $this->falha);
            $p = substr(parse_url($u, PHP_URL_PATH), 3);

            return match (true) {
                $p === '/oauth/token' => $r['code'] === 'bom'
                    ? Http::response(['access_token' => 'ntn_oauth' . str_repeat('x', 30), 'bot_id' => 'bot-1', 'workspace_id' => 'ws-1', 'workspace_name' => 'Meu espaço'])
                    : Http::response(['error' => 'invalid_grant'], 400),
                $p === '/users/me' => $r->header('Authorization')[0] === 'Bearer ' . self::token()
                    ? Http::response(['object' => 'user', 'id' => 'bot-2', 'type' => 'bot', 'name' => 'WF', 'bot' => ['workspace_name' => 'Espaço do token']])
                    : Http::response(['object' => 'error', 'code' => 'unauthorized'], 401),
                $p === '/search' => Http::response(['results' => [
                    ['object' => 'page', 'id' => self::PAGINA, 'last_edited_time' => '2026-11-01T10:00:00.000Z', 'url' => 'https://www.notion.so/Plano', 'icon' => ['type' => 'emoji', 'emoji' => '🚀'],
                        'parent' => ['type' => 'workspace'], 'properties' => ['title' => ['type' => 'title', 'title' => [$rt('Plano do projeto')]]]],
                    ['object' => 'database', 'id' => self::BANCO, 'last_edited_time' => '2026-11-02T10:00:00.000Z', 'url' => 'https://www.notion.so/Tarefas',
                        'parent' => ['type' => 'page_id', 'page_id' => self::PAGINA], 'title' => [$rt('Tarefas')]],
                    ['object' => 'page', 'id' => 'velha', 'archived' => true, 'properties' => []],
                ], 'has_more' => false]),
                $p === '/pages/' . self::PAGINA => Http::response(['object' => 'page', 'id' => self::PAGINA, 'last_edited_time' => '2026-11-01T10:00:00.000Z', 'url' => 'https://www.notion.so/Plano',
                    'parent' => ['type' => 'workspace'], 'properties' => [
                        'Name'   => ['type' => 'title', 'title' => [$rt('Plano do projeto')]],
                        'Status' => ['type' => 'status', 'status' => ['name' => 'Em andamento']],
                    ]]),
                $p === '/pages/' . self::BANCO, $p === '/pages/' . self::SUMIU, $p === '/databases/' . self::SUMIU => Http::response(['object' => 'error', 'code' => 'object_not_found'], 404),
                $p === '/blocks/' . self::PAGINA . '/children' => Http::response(['results' => [
                    $bloco('b1', 'heading_1', ['rich_text' => [$rt('Objetivo')]]),
                    $bloco('b2', 'paragraph', ['rich_text' => [$rt('Lançar '), $rt('rápido', ['bold' => true]), $rt(' com '), $rt('site', [], 'https://exemplo.com')]]),
                    $bloco('b3', 'bulleted_list_item', ['rich_text' => [$rt('Item A')]], true),
                    $bloco('b4', 'to_do', ['rich_text' => [$rt('Feito')], 'checked' => true]),
                    $bloco('b5', 'numbered_list_item', ['rich_text' => [$rt('Um')]]),
                    $bloco('b6', 'numbered_list_item', ['rich_text' => [$rt('Dois')]]),
                    $bloco('b7', 'code', ['rich_text' => [$rt('echo 1;')], 'language' => 'php']),
                    $bloco('b8', 'table', ['table_width' => 2], true),
                    $bloco('b9', 'child_page', ['title' => 'Subpágina']),
                    $bloco('b10', 'image', ['type' => 'file', 'file' => ['url' => 'https://s3.exemplo/x.png'], 'caption' => []]),
                ], 'has_more' => false]),
                $p === '/blocks/b3/children' => Http::response(['results' => [$bloco('b3a', 'bulleted_list_item', ['rich_text' => [$rt('Sub A')]])], 'has_more' => false]),
                $p === '/blocks/b8/children' => Http::response(['results' => [
                    ['object' => 'block', 'id' => 'r1', 'type' => 'table_row', 'table_row' => ['cells' => [[$rt('Col 1')], [$rt('Col 2')]]]],
                    ['object' => 'block', 'id' => 'r2', 'type' => 'table_row', 'table_row' => ['cells' => [[$rt('a|b')], [$rt('c')]]]],
                ], 'has_more' => false]),
                $p === '/databases/' . self::BANCO => Http::response(['object' => 'database', 'id' => self::BANCO, 'last_edited_time' => '2026-11-02T10:00:00.000Z', 'url' => 'https://www.notion.so/Tarefas',
                    'title' => [$rt('Tarefas')], 'parent' => ['type' => 'page_id', 'page_id' => self::PAGINA], 'properties' => [
                        'Prazo' => ['type' => 'date'], 'Nome' => ['type' => 'title'], 'Feita' => ['type' => 'checkbox'], 'Tags' => ['type' => 'multi_select'],
                    ]]),
                $p === '/databases/' . self::BANCO . '/query' => Http::response(['results' => [
                    ['object' => 'page', 'properties' => [
                        'Nome'  => ['type' => 'title', 'title' => [$rt('Comprar domínio')]],
                        'Prazo' => ['type' => 'date', 'date' => ['start' => '2026-11-20', 'end' => null]],
                        'Feita' => ['type' => 'checkbox', 'checkbox' => true],
                        'Tags'  => ['type' => 'multi_select', 'multi_select' => [['name' => 'site'], ['name' => 'urgente']]],
                    ]],
                ], 'has_more' => false]),
                default => Http::response(['object' => 'error', 'code' => 'object_not_found'], 404),
            };
        });
    }

    private function conta(): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => 'basico', 'plano_expira_em' => now()->addMonth()]);

        return $u->fresh();
    }

    private function conectada(?User $u = null): User
    {
        $u ??= $this->conta();
        $i = new NotionIntegracao(['access_token' => self::token(), 'tipo' => 'interno', 'workspace_nome' => 'Espaço']);
        $i->user_id = $u->id;
        $i->save();

        return $u;
    }

    public function test_status_sem_conexao_e_com_oauth_desligado(): void
    {
        $this->actingAs($this->conta())->getJson('/integracoes/notion')
            ->assertOk()->assertJson(['conectado' => false, 'oauth' => false]);
        $this->getJson('/integracoes/notion/conectar')->assertNotFound();
    }

    public function test_token_interno_e_conferido_e_guardado_criptografado(): void
    {
        $u = $this->conta();
        $this->actingAs($u)->postJson('/integracoes/notion/token', ['token' => 'qualquer-coisa'])->assertStatus(422)->assertJsonValidationErrors('token');
        $this->postJson('/integracoes/notion/token', ['token' => 'ntn_' . str_repeat('z', 30)])->assertStatus(422)->assertJsonValidationErrors('token');

        $r = $this->postJson('/integracoes/notion/token', ['b64' => base64_encode(json_encode(['token' => self::token()]))])
            ->assertOk()->assertJson(['conectado' => true, 'tipo' => 'interno', 'workspace' => 'Espaço do token']);
        $this->assertStringNotContainsString(self::token(), $r->getContent());

        $bruto = DB::table('notion_integracoes')->where('user_id', $u->id)->value('access_token');
        $this->assertNotSame(self::token(), $bruto);
        $this->assertSame(self::token(), NotionIntegracao::where('user_id', $u->id)->first()->access_token);
    }

    public function test_oauth_ida_e_volta(): void
    {
        config(['services.notion.client_id' => 'cliente', 'services.notion.client_secret' => 'segredo']);
        $u = $this->conta();
        $this->actingAs($u)->getJson('/integracoes/notion')->assertJson(['oauth' => true, 'retorno' => url('/integracoes/notion/retorno')]);

        $ida = $this->get('/integracoes/notion/conectar')->assertRedirect();
        $destino = $ida->headers->get('Location');
        $this->assertStringStartsWith('https://api.notion.com/v1/oauth/authorize?', $destino);
        parse_str(parse_url($destino, PHP_URL_QUERY), $q);
        $this->assertSame('cliente', $q['client_id']);
        $this->assertSame('user', $q['owner']);
        $this->assertSame(url('/integracoes/notion/retorno'), $q['redirect_uri']);
        // o state só tem letras e números: nada que o mod_security confunda com um endereço
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $q['state']);

        // estado errado: não troca o código
        $this->get('/integracoes/notion/retorno?code=bom&state=outro')->assertRedirect(route('areas.negocios'))->assertSessionHas('notion_erro');
        $this->assertDatabaseCount('notion_integracoes', 0);

        // o state vale uma vez só; volta com o certo
        $this->get('/integracoes/notion/conectar');
        $estado = session('notion_state')['v'];
        $this->get('/integracoes/notion/retorno?code=bom&state=' . $estado)->assertRedirect(route('areas.negocios'))->assertSessionHas('notion_ok');
        $i = NotionIntegracao::where('user_id', $u->id)->first();
        $this->assertSame('oauth', $i->tipo);
        $this->assertSame('Meu espaço', $i->workspace_nome);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/oauth/token')
            && $r->header('Authorization')[0] === 'Basic ' . base64_encode('cliente:segredo')
            && $r['redirect_uri'] === url('/integracoes/notion/retorno') && $r['grant_type'] === 'authorization_code');
        $this->get('/integracoes/notion/retorno?code=bom&state=' . $estado)->assertSessionHas('notion_erro');

        // cancelado no Notion
        $this->get('/integracoes/notion/conectar');
        $this->get('/integracoes/notion/retorno?error=access_denied&state=' . session('notion_state')['v'])->assertSessionHas('notion_erro', 'A conexão foi cancelada no Notion.');

        // código recusado
        $this->get('/integracoes/notion/conectar');
        $this->get('/integracoes/notion/retorno?code=ruim&state=' . session('notion_state')['v'])->assertSessionHas('notion_erro');
    }

    public function test_redirect_configurado_substitui_o_padrao(): void
    {
        config(['services.notion.client_id' => 'c', 'services.notion.client_secret' => 's', 'services.notion.redirect' => 'https://repasse.exemplo/notion/']);
        $this->assertSame('https://repasse.exemplo/notion/', Notion::retorno());
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://repasse.exemplo/notion/'), Notion::urlAutorizacao('abc'));
    }

    public function test_lista_paginas_e_bancos(): void
    {
        $this->actingAs($this->conectada())->getJson('/integracoes/notion/paginas?q=plano')
            ->assertOk()
            ->assertJsonCount(2, 'itens')
            ->assertJsonPath('itens.0', ['id' => self::PAGINA, 'tipo' => 'pagina', 'titulo' => 'Plano do projeto', 'icone' => '🚀', 'editado' => '2026-11-01T10:00:00.000Z', 'url' => 'https://www.notion.so/Plano', 'pai' => null])
            ->assertJsonPath('itens.1.tipo', 'banco')
            ->assertJsonPath('itens.1.pai', self::PAGINA);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/search') && $r['query'] === 'plano'
            && $r->header('Notion-Version')[0] === Notion::VERSAO);
    }

    public function test_pagina_vira_markdown_e_banco_vira_tabela(): void
    {
        $r = $this->actingAs($this->conectada())->postJson('/integracoes/notion/conteudo', ['itens' => [
            ['id' => self::PAGINA, 'tipo' => 'pagina'], ['id' => self::BANCO], ['id' => self::SUMIU, 'tipo' => 'pagina'],
        ]])->assertOk();

        $md = $r->json('itens.0.texto');
        $this->assertSame('pagina', $r->json('itens.0.tipo'));
        $this->assertStringContainsString("- **Status:** Em andamento\n\n# Objetivo", $md);
        $this->assertStringContainsString('Lançar **rápido** com [site](https://exemplo.com)', $md);
        $this->assertStringContainsString("- Item A\n    - Sub A\n- [x] Feito\n1. Um\n2. Dois", $md);
        $this->assertStringContainsString("```php\necho 1;\n```", $md);
        $this->assertStringContainsString("| Col 1 | Col 2 |\n| --- | --- |\n| a\\|b | c |", $md);
        $this->assertStringContainsString('📄 [[Subpágina]]', $md);
        $this->assertStringContainsString('🖼 image (arquivo no Notion)', $md);
        $this->assertStringNotContainsString('s3.exemplo', $md); // link temporário do Notion não fica no cartão

        // banco: a coluna do título vem primeiro
        $r->assertJsonPath('itens.1.tipo', 'banco')
            ->assertJsonPath('itens.1.cab', ['Nome', 'Prazo', 'Feita', 'Tags'])
            ->assertJsonPath('itens.1.linhas', [['Comprar domínio', '2026-11-20', '✔', 'site, urgente']])
            ->assertJsonPath('erros.0.id', self::SUMIU);
        $this->assertNotNull(NotionIntegracao::first()->ultimo_sync_em);
    }

    public function test_conteudo_valida_e_exige_conexao(): void
    {
        $u = $this->conta();
        $this->actingAs($u)->postJson('/integracoes/notion/conteudo', ['itens' => [['id' => self::PAGINA]]])->assertStatus(409);
        $this->conectada($u);
        $this->postJson('/integracoes/notion/conteudo', ['itens' => [['id' => '../users/me']]])->assertStatus(422);
        $this->postJson('/integracoes/notion/conteudo', ['itens' => array_fill(0, 11, ['id' => self::PAGINA])])->assertStatus(422);
    }

    public function test_token_revogado_avisa_para_conectar_de_novo(): void
    {
        $this->actingAs($this->conectada());
        $this->falha = 401;
        $this->postJson('/integracoes/notion/conteudo', ['itens' => [['id' => self::PAGINA]]])->assertStatus(409)->assertJsonFragment(['message' => 'O Notion recusou o acesso (a conexão foi removida ou o token mudou). Conecte de novo.']);
        $this->getJson('/integracoes/notion/paginas')->assertStatus(409);
        $this->getJson('/integracoes/notion')->assertJsonPath('ultimo_erro', 'O Notion recusou o acesso (a conexão foi removida ou o token mudou). Conecte de novo.');
    }

    public function test_cada_conta_ve_so_a_sua_conexao_e_pode_desconectar(): void
    {
        $dono = $this->conectada();
        $outro = $this->conta();
        $this->actingAs($outro)->getJson('/integracoes/notion')->assertJson(['conectado' => false]);
        $this->postJson('/integracoes/notion/conteudo', ['itens' => [['id' => self::PAGINA]]])->assertStatus(409);
        $this->deleteJson('/integracoes/notion')->assertOk();
        $this->assertDatabaseHas('notion_integracoes', ['user_id' => $dono->id]);

        $this->actingAs($dono)->deleteJson('/integracoes/notion')->assertOk()->assertJson(['conectado' => false]);
        $this->assertDatabaseCount('notion_integracoes', 0);
    }

    public function test_visitante_nao_entra(): void
    {
        $this->getJson('/integracoes/notion')->assertUnauthorized();
        $this->get('/integracoes/notion/retorno?code=x&state=y')->assertRedirect(route('login'));
    }

    public function test_pagina_de_integracoes_mostra_o_notion_e_o_obsidian(): void
    {
        $this->actingAs($this->conta())->get('/areas/negocios')->assertOk()
            ->assertSee('wfNotion(', false)->assertSee('Obsidian')->assertSee('js/notas.js', false);
    }
}
