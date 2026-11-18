<?php

namespace Tests\Feature;

use App\Models\NotionIntegracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Mini Notion no cartão do quadro: abrir página/banco, editar blocos, criar, renomear, mover e arquivar páginas. */
class NotionEditarTest extends TestCase
{
    use RefreshDatabase;

    private const PAG = '11111111-2222-3333-4444-555555555555';
    private const BANCO = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private const BLOCO = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const OUTRA = '22222222-3333-4444-5555-666666666666';

    private static function token(): string { return 'ntn_' . str_repeat('c3D4', 10); }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $rt = fn (string $t) => ['type' => 'text', 'plain_text' => $t, 'annotations' => []];
        Http::fake(function (HttpRequest $r) use ($rt) {
            $p = substr((string) parse_url($r->url(), PHP_URL_PATH), 3);
            $m = $r->method();

            return match (true) {
                $m === 'GET' && $p === '/pages/' . self::PAG => Http::response(['object' => 'page', 'id' => self::PAG, 'parent' => ['type' => 'workspace'], 'url' => 'https://www.notion.so/x',
                    'properties' => ['Nome' => ['type' => 'title', 'title' => [$rt('Plano')]], 'Status' => ['type' => 'select', 'select' => ['name' => 'Ativo']]]]),
                $m === 'GET' && $p === '/blocks/' . self::PAG . '/children' => Http::response(['results' => [
                    ['object' => 'block', 'id' => self::BLOCO, 'type' => 'to_do', 'has_children' => false, 'to_do' => ['rich_text' => [$rt('Ligar')], 'checked' => false]],
                    ['object' => 'block', 'id' => 'c1', 'type' => 'child_page', 'has_children' => true, 'child_page' => ['title' => 'Sub']],
                    ['object' => 'block', 'id' => 'd1', 'type' => 'divider', 'has_children' => false, 'divider' => []],
                ], 'has_more' => false]),
                $m === 'GET' && $p === '/databases/' . self::BANCO => Http::response(['object' => 'database', 'id' => self::BANCO, 'title' => [$rt('Tarefas')],
                    'properties' => ['Tarefa' => ['type' => 'title'], 'Prazo' => ['type' => 'date']]]),
                $m === 'POST' && $p === '/databases/' . self::BANCO . '/query' => Http::response(['results' => [
                    ['object' => 'page', 'id' => self::OUTRA, 'properties' => ['Tarefa' => ['type' => 'title', 'title' => [$rt('Comprar')]], 'Prazo' => ['type' => 'date', 'date' => ['start' => '2026-12-01']]]],
                ], 'has_more' => false]),
                $m === 'PATCH' && $p === '/blocks/' . self::BLOCO => Http::response(['object' => 'block']),
                $m === 'DELETE' && $p === '/blocks/' . self::BLOCO => Http::response(['object' => 'block', 'archived' => true]),
                $m === 'PATCH' && $p === '/blocks/' . self::PAG . '/children' => Http::response(['results' => [
                    ['object' => 'block', 'id' => 'novo-1', 'type' => 'heading_2', 'has_children' => false, 'heading_2' => ['rich_text' => [$rt('Metas')]]],
                ]]),
                $m === 'POST' && $p === '/pages' => Http::response(['object' => 'page', 'id' => self::OUTRA, 'parent' => ['type' => 'database_id', 'database_id' => self::BANCO],
                    'properties' => ['Tarefa' => ['type' => 'title', 'title' => [$rt('Nova')]]]]),
                $m === 'PATCH' && $p === '/pages/' . self::PAG => Http::response(['object' => 'page']),
                $m === 'POST' && $p === '/pages/' . self::PAG . '/move' => Http::response(['object' => 'page']),
                default => Http::response(['object' => 'error', 'code' => 'restricted_resource'], 403),
            };
        });
    }

    private function conectada(): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => 'basico', 'plano_expira_em' => now()->addMonth()]);
        $i = new NotionIntegracao(['access_token' => self::token(), 'tipo' => 'interno', 'workspace_nome' => 'Espaço']);
        $i->user_id = $u->id;
        $i->save();

        return $u->fresh();
    }

    private static function b64(array $d): array { return ['b64' => base64_encode(json_encode($d))]; }

    public function test_abre_pagina_com_blocos_editaveis_e_banco_com_linhas(): void
    {
        $u = $this->conectada();
        $j = $this->actingAs($u)->getJson('/integracoes/notion/paginas/' . self::PAG)->assertOk()->json('pagina');
        $this->assertSame('Plano', $j['titulo']);
        $this->assertSame([['nome' => 'Status', 'valor' => 'Ativo']], $j['propriedades']);
        $this->assertSame(['id' => self::BLOCO, 'tipo' => 'to_do', 'editavel' => true, 'filhos' => false, 'texto' => 'Ligar', 'marcado' => false], $j['blocos'][0]);
        $this->assertSame(['child_page', 'Sub', false], [$j['blocos'][1]['tipo'], $j['blocos'][1]['texto'], $j['blocos'][1]['editavel']]);
        $this->assertFalse($j['blocos'][2]['editavel']);

        $banco = $this->actingAs($u)->getJson('/integracoes/notion/bancos/' . self::BANCO . '/linhas')->assertOk()->json('banco');
        $this->assertSame(['Prazo'], $banco['colunas']);
        $this->assertSame(['Comprar', ['2026-12-01']], [$banco['linhas'][0]['titulo'], $banco['linhas'][0]['valores']]);
    }

    public function test_edita_marca_cria_e_apaga_blocos(): void
    {
        $u = $this->conectada();
        $this->actingAs($u)->patchJson('/integracoes/notion/blocos/' . self::BLOCO, self::b64(['tipo' => 'to_do', 'texto' => 'Ligar às 10h', 'marcado' => true]))->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/blocks/' . self::BLOCO)
            && $r['to_do']['rich_text'][0]['text']['content'] === 'Ligar às 10h' && $r['to_do']['checked'] === true);

        // só marcar: não reescreve o texto
        $this->actingAs($u)->patchJson('/integracoes/notion/blocos/' . self::BLOCO, self::b64(['tipo' => 'to_do', 'marcado' => false]))->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && $r->data() === ['to_do' => ['checked' => false]]);

        $novo = $this->actingAs($u)->postJson('/integracoes/notion/paginas/' . self::PAG . '/blocos', self::b64(['tipo' => 'heading_2', 'texto' => 'Metas', 'depois' => self::BLOCO]))
            ->assertOk()->json('bloco');
        $this->assertSame(['novo-1', 'heading_2', 'Metas'], [$novo['id'], $novo['tipo'], $novo['texto']]);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && ($r['after'] ?? null) === self::BLOCO && $r['children'][0]['type'] === 'heading_2');

        $this->actingAs($u)->deleteJson('/integracoes/notion/blocos/' . self::BLOCO)->assertOk();
        $this->actingAs($u)->patchJson('/integracoes/notion/blocos/' . self::BLOCO, self::b64(['tipo' => 'image', 'texto' => 'x']))->assertStatus(422);
    }

    public function test_cria_linha_no_banco_renomeia_move_e_arquiva(): void
    {
        $u = $this->conectada();
        $this->actingAs($u)->postJson('/integracoes/notion/paginas', self::b64(['pai' => self::BANCO, 'pai_tipo' => 'banco', 'titulo' => 'Nova']))->assertOk()
            ->assertJsonPath('pagina.titulo', 'Nova');
        // no banco o título vai na propriedade de título dele ("Tarefa"), não em "title"
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/pages') && isset($r['properties']['Tarefa']) && $r['parent'] === ['database_id' => self::BANCO]);

        $this->actingAs($u)->patchJson('/integracoes/notion/paginas/' . self::PAG, self::b64(['titulo' => 'Plano 2027']))->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/pages/' . self::PAG) && isset($r['properties']['Nome']));

        $this->actingAs($u)->patchJson('/integracoes/notion/paginas/' . self::PAG . '/mover', ['pai' => self::OUTRA])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/pages/' . self::PAG . '/move') && $r['parent']['page_id'] === self::OUTRA);
        $this->actingAs($u)->patchJson('/integracoes/notion/paginas/' . self::PAG . '/mover', ['pai' => self::PAG])->assertStatus(422);

        $this->actingAs($u)->patchJson('/integracoes/notion/paginas/' . self::PAG . '/arquivar', ['arquivar' => true])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && ($r['archived'] ?? null) === true);
    }

    public function test_sem_permissao_de_edicao_explica_e_sem_conexao_pede_para_conectar(): void
    {
        $u = $this->conectada();
        $this->actingAs($u)->deleteJson('/integracoes/notion/blocos/' . self::OUTRA)->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Capacidades'));

        $this->actingAs(User::factory()->create())->getJson('/integracoes/notion/paginas/' . self::PAG)->assertStatus(409);
        $this->actingAs($u)->getJson('/integracoes/notion/paginas/nao-e-id')->assertStatus(404);
    }
}
