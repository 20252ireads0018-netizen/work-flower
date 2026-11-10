<?php

namespace Tests\Feature;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
use App\Models\User;
use App\Support\AbasRetiradas;
use App\Support\AreasConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Áreas por conta (esconder, renomear, ordem, áreas criadas), mover cartões entre áreas sem perder nada
 * e a retirada das abas "Trabalho" (Carteira) e "Mapa mental" (Corpo, Mente, Carteira).
 */
class AreasPersonalizaveisTest extends TestCase
{
    use RefreshDatabase;

    private function estado(string $classe, int $uid, string $chave = 'estado'): array
    {
        return json_decode($classe::where('user_id', $uid)->where('chave', $chave)->value('valor'), true);
    }

    private function estadoBruto(string $classe, int $uid, string $chave = 'estado'): string
    {
        return $classe::where('user_id', $uid)->where('chave', $chave)->value('valor');
    }

    private function gravar(string $classe, int $uid, string $chave, $valor): void
    {
        $classe::create(['user_id' => $uid, 'chave' => $chave, 'valor' => is_string($valor) ? $valor : json_encode($valor)]);
    }

    /* ================================================================ abas retiradas */

    public function test_carteira_trabalho_e_mapa_vao_para_o_quadro_livre_sem_perder_nada(): void
    {
        $u = User::factory()->create();
        $this->gravar(CarteiraDado::class, $u->id, 'estado', '{"mapaSemeado":true,"contas":[],"projetos":[{"id":"p1","nome":"Site","status":"fazer","nota":"","doc":"","anexos":[]}],'
            . '"execucoes":{},"layout":{"livre":{"bl1":{"x":0,"y":0,"w":360,"h":200,"z":2}},"trabalho":{"projetos":{"x":0,"y":0,"w":1088,"h":440,"z":1},"bt1":{"x":10,"y":460,"w":300,"h":100,"z":3,"cor":"#f472b6"}},'
            . '"mapa":{"mapa-inicial":{"x":0,"y":0,"w":1088,"h":560,"z":1}},"carteira":{}},'
            . '"blocos":[{"id":"bl1","secao":"livre","tipo":"texto","titulo":"Livre","links":[],"dados":{"html":"a"}},'
            . '{"id":"bt1","secao":"trabalho","tipo":"lista","titulo":"Do trabalho","links":[],"dados":{"itens":[]}},'
            . '{"id":"mapa-inicial","secao":"mapa","tipo":"mapa","titulo":"Mapa mental","links":[],"dados":{"nos":[{"id":"n1","t":"Tema"}]}}]}');
        $this->gravar(CarteiraDado::class, $u->id, 'img.bt1', '"data:image/png;base64,AAA"');

        $this->actingAs($u)->get('/areas/carteira')->assertOk()->assertDontSee("aba === 'trabalho'", false)->assertDontSee("aba === 'mapa'", false);

        $bruto = $this->estadoBruto(CarteiraDado::class, $u->id);
        $e = json_decode($bruto, true);
        $this->assertSame(['livre', 'livre', 'livre'], array_column($e['blocos'], 'secao'));
        $this->assertSame(['bl1', 'bt1', 'mapa-inicial'], array_column($e['blocos'], 'id'));
        $this->assertSame(['n1'], array_column($e['blocos'][2]['dados']['nos'], 'id'));
        $this->assertArrayNotHasKey('trabalho', $e['layout']);
        $this->assertArrayNotHasKey('mapa', $e['layout']);
        $L = $e['layout']['livre'];
        // abaixo do que já havia no Quadro livre (200 + 16), mantendo a distância entre os cartões e a cor
        $this->assertSame(216, $L['projetos']['y']);
        $this->assertSame(216 + 460, $L['bt1']['y']);
        $this->assertSame('#f472b6', $L['bt1']['cor']);
        $this->assertSame(216 + 460 + 100 + 16, $L['mapa-inicial']['y']);
        // nada de outros dados mudou, e objetos vazios continuam objetos ({} e não [])
        $this->assertSame('Site', $e['projetos'][0]['nome']);
        $this->assertStringContainsString('"execucoes":{}', $bruto);
        $this->assertStringContainsString('"carteira":{}', $bruto);
        // a imagem continua na mesma chave (o id do bloco não muda)
        $this->assertTrue(CarteiraDado::where('user_id', $u->id)->where('chave', 'img.bt1')->exists());

        // abrir de novo não muda mais nada
        $this->actingAs($u)->get('/areas/carteira')->assertOk();
        $this->assertSame($bruto, $this->estadoBruto(CarteiraDado::class, $u->id));
    }

    public function test_projetos_vazio_nao_traz_o_cartao_para_o_quadro_livre(): void
    {
        $e = AbasRetiradas::decodificar('{"projetos":[],"layout":{"trabalho":{"projetos":{"x":0,"y":0,"w":1088,"h":440}}},"blocos":[]}');
        $this->assertTrue(AbasRetiradas::migrar($e, 'carteira'));
        $this->assertSame([], (array) $e->layout->livre);
    }

    public function test_mente_e_corpo_mapa_mental_vai_para_o_quadro_livre(): void
    {
        $u = User::factory()->create();
        $estado = ['mapaSemeado' => true, 'layout' => ['mapa' => ['mapa-inicial' => ['x' => 0, 'y' => 40, 'w' => 1088, 'h' => 560, 'z' => 1]]],
            'blocos' => [['id' => 'mapa-inicial', 'secao' => 'mapa', 'tipo' => 'mapa', 'titulo' => 'Mapa', 'links' => [], 'dados' => ['nos' => []]]]];
        $this->gravar(CorpoDado::class, $u->id, 'estado', $estado);
        $this->gravar(CorpoDado::class, $u->id, 'mente.estado', $estado);

        $this->actingAs($u)->get('/areas/corpo')->assertOk();
        $this->actingAs($u)->get('/areas/mente')->assertOk();

        foreach (['estado', 'mente.estado'] as $k) {
            $e = $this->estado(CorpoDado::class, $u->id, $k);
            $this->assertSame('livre', $e['blocos'][0]['secao']);
            $this->assertSame(0, $e['layout']['livre']['mapa-inicial']['y']); // quadro livre vazio: começa no topo
            $this->assertArrayNotHasKey('mapa', $e['layout']);
        }
    }

    public function test_migracao_do_banco_corrige_os_estados_guardados(): void
    {
        $u = User::factory()->create();
        $this->gravar(CarteiraDado::class, $u->id, 'estado', ['layout' => ['mapa' => []], 'blocos' => [['id' => 'm', 'secao' => 'mapa', 'tipo' => 'mapa']]]);
        $this->gravar(CorpoDado::class, $u->id, 'mente.estado', ['layout' => [], 'blocos' => [['id' => 'm', 'secao' => 'mapa', 'tipo' => 'mapa']]]);
        $this->gravar(CorpoDado::class, $u->id, 'estado', ['layout' => [], 'blocos' => [['id' => 'x', 'secao' => 'livre', 'tipo' => 'texto', 'titulo' => 'mapa']]]);
        $antesCorpo = $this->estadoBruto(CorpoDado::class, $u->id);

        $m = require database_path('migrations/2026_11_09_000000_retira_abas_trabalho_e_mapa_mental.php');
        $m->up();

        $this->assertSame('livre', $this->estado(CarteiraDado::class, $u->id)['blocos'][0]['secao']);
        $this->assertSame('livre', $this->estado(CorpoDado::class, $u->id, 'mente.estado')['blocos'][0]['secao']);
        $this->assertSame($antesCorpo, $this->estadoBruto(CorpoDado::class, $u->id)); // nada a mudar: não regrava
    }

    /* ================================================================ áreas da conta */

    public function test_esconder_renomear_e_reordenar_areas(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/areas-config', [
            'ordem' => ['diverso', 'mente', 'corpo'], 'ocultas' => ['corpo'], 'nomes' => ['mente' => 'Estudos', 'carteira' => '  '],
        ])->assertOk();

        $l = AreasConta::lista($u->fresh());
        $this->assertSame(['diverso', 'mente', 'corpo', 'carteira', 'negocios'], array_keys($l));
        $this->assertTrue($l['corpo']['oculta']);
        $this->assertSame('Estudos', $l['mente']['nome']);
        $this->assertSame('Carteira', $l['carteira']['nome']);

        $painel = $this->actingAs($u->fresh())->get('/painel')->assertOk();
        $painel->assertSee('Estudos')->assertSee('Personalizar áreas');
        $this->assertStringNotContainsString('>Corpo</a>', $painel->getContent());
        // escondida continua abrindo (os dados não somem) e a página usa o nome novo
        $this->actingAs($u->fresh())->get('/areas/corpo')->assertOk();
        $this->actingAs($u->fresh())->get('/areas/mente')->assertOk()->assertSee('Estudos');
    }

    public function test_nao_deixa_esconder_todas(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/areas-config', ['ocultas' => ['corpo', 'mente', 'carteira', 'negocios', 'diverso']])->assertStatus(422);
    }

    public function test_criar_area_abre_a_pagina_e_aparece_no_menu(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->postJson('/areas-config/extras', ['nome' => 'Trabalho'])->assertOk();
        $id = $r->json('criada.id');
        $this->assertMatchesRegularExpression('/^x[a-z0-9]{8}$/', $id);

        $p = $this->actingAs($u->fresh())->get('/areas/extra/' . $id)->assertOk()->assertSee('Trabalho');
        $this->assertStringContainsString('"grupo":"' . $id . '"', $p->getContent());
        $this->actingAs($u->fresh())->get('/painel')->assertSee('/areas/extra/' . $id, false);
        // de outra conta não abre
        $this->actingAs(User::factory()->create())->get('/areas/extra/' . $id)->assertNotFound();
    }

    public function test_excluir_area_criada_leva_as_abas_para_o_diverso_ou_apaga(): void
    {
        $u = User::factory()->create();
        $a = AreasConta::criarExtra($u, 'Casa')['id'];
        $b = AreasConta::criarExtra($u->fresh(), 'Viagem')['id'];
        $this->gravar(DiversoDado::class, $u->id, 'estado', ['v' => 2,
            'abas' => [['id' => 'quadro', 'nome' => 'Geral', 'icone' => 'pin'], ['id' => 'ta', 'nome' => 'Cozinha', 'icone' => 'casa', 'grupo' => $a], ['id' => 'tb', 'nome' => 'Roteiro', 'icone' => 'viagem', 'grupo' => $b]],
            'layout' => ['ta' => ['c1' => ['x' => 0, 'y' => 0, 'w' => 300, 'h' => 200]], 'tb' => ['c2' => ['x' => 0, 'y' => 0, 'w' => 300, 'h' => 200]]],
            'blocos' => [['id' => 'c1', 'secao' => 'ta', 'tipo' => 'texto'], ['id' => 'c2', 'secao' => 'tb', 'tipo' => 'imagem']]]);
        $this->gravar(DiversoDado::class, $u->id, 'img.c2', '"x"');

        $this->actingAs($u->fresh())->deleteJson("/areas-config/extras/$a", ['destino' => 'diverso'])->assertOk();
        $e = $this->estado(DiversoDado::class, $u->id);
        $this->assertArrayNotHasKey('grupo', $e['abas'][1]);
        $this->assertSame('c1', $e['blocos'][0]['id']);

        $this->actingAs($u->fresh())->deleteJson("/areas-config/extras/$b", ['destino' => 'apagar'])->assertOk();
        $e = $this->estado(DiversoDado::class, $u->id);
        $this->assertSame(['quadro', 'ta'], array_column($e['abas'], 'id'));
        $this->assertSame(['c1'], array_column($e['blocos'], 'id'));
        $this->assertFalse(DiversoDado::where('user_id', $u->id)->where('chave', 'img.c2')->exists());
        $this->assertSame([], AreasConta::config($u->fresh())['extras']);
    }

    /* ================================================================ mover cartões */

    private function corpoComCartoes(User $u): void
    {
        $this->gravar(CorpoDado::class, $u->id, 'estado', ['mapaSemeado' => true, 'treinos' => [['id' => 't1', 'nome' => 'Pernas']], 'agua' => ['hist' => new \stdClass],
            'layout' => ['treinos' => ['treino-lista' => ['x' => 0, 'y' => 0, 'w' => 500, 'h' => 300], 'p1' => ['x' => 0, 'y' => 320, 'w' => 400, 'h' => 300, 'cor' => '#2dd4bf'], 'f1' => ['x' => 410, 'y' => 330, 'w' => 300, 'h' => 200]],
                'livre' => ['mapa-inicial' => ['x' => 0, 'y' => 0, 'w' => 1088, 'h' => 560], 'doc' => ['x' => 0, 'y' => 600, 'w' => 300, 'h' => 100]]],
            'blocos' => [
                ['id' => 'p1', 'secao' => 'treinos', 'tipo' => 'pasta', 'titulo' => 'Fichas'],
                ['id' => 'f1', 'secao' => 'treinos', 'tipo' => 'imagem', 'titulo' => 'Foto', 'pasta' => 'p1', 'links' => [['t' => 'tarefa', 'id' => 3]]],
                ['id' => 'mapa-inicial', 'secao' => 'livre', 'tipo' => 'mapa', 'titulo' => 'Mapa', 'dados' => ['nos' => []]],
                ['id' => 'pdf1', 'secao' => 'livre', 'tipo' => 'editorpdf', 'titulo' => 'Contrato', 'dados' => ['pdf' => ['v' => 2, 'fontes' => ['p' => ['id' => 'arq1', 'v' => '1']]]]],
            ]]);
        $this->gravar(CorpoDado::class, $u->id, 'img.f1', '"data:image/png;base64,FOTO"');
        $this->gravar(CorpoDado::class, $u->id, 'img.f1.camadas', '{"c":1}');
        $this->gravar(CorpoDado::class, $u->id, 'img.f10', '"outro bloco"'); // não pode ir junto (prefixo parecido)
        $this->gravar(CorpoDado::class, $u->id, 'img.mapa-inicial', '"m"');
    }

    public function test_mover_aba_do_corpo_para_aba_nova_no_diverso_leva_cartoes_layout_e_imagens(): void
    {
        $u = User::factory()->create();
        $this->corpoComCartoes($u);
        $this->gravar(DiversoDado::class, $u->id, 'estado', ['v' => 2, 'abas' => [['id' => 'quadro', 'nome' => 'Geral', 'icone' => 'pin']],
            'layout' => ['quadro' => []], 'blocos' => [['id' => 'mapa-inicial', 'secao' => 'quadro', 'tipo' => 'mapa']]]);

        $r = $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'corpo', 'aba' => 'treinos', 'para' => 'diverso'])->assertOk();
        $this->assertSame(2, $r->json('movidos'));
        $aba = $r->json('aba');

        $d = $this->estado(DiversoDado::class, $u->id);
        $this->assertSame(['id' => $aba, 'nome' => 'Treinos', 'icone' => 'pasta'], $d['abas'][1]);
        $movidos = array_values(array_filter($d['blocos'], fn ($b) => $b['secao'] === $aba));
        $this->assertSame(['p1', 'f1'], array_column($movidos, 'id'));
        $this->assertSame('p1', $movidos[1]['pasta']);   // continua dentro da pasta
        $this->assertSame([], $movidos[1]['links']);     // vínculos eram do Corpo
        $L = $d['layout'][$aba];
        $this->assertSame(['x' => 0, 'y' => 0, 'w' => 400, 'h' => 300, 'cor' => '#2dd4bf'], $L['p1']);
        $this->assertSame(10, $L['f1']['y']);
        $this->assertSame('"data:image/png;base64,FOTO"', DiversoDado::where('user_id', $u->id)->where('chave', 'img.f1')->value('valor'));
        $this->assertTrue(DiversoDado::where('user_id', $u->id)->where('chave', 'img.f1.camadas')->exists());

        $c = $this->estado(CorpoDado::class, $u->id);
        $this->assertSame(['mapa-inicial', 'pdf1'], array_column($c['blocos'], 'id'));
        $this->assertSame(['treino-lista'], array_keys($c['layout']['treinos'])); // o cartão fixo fica
        $this->assertSame('Pernas', $c['treinos'][0]['nome']);
        $this->assertFalse(CorpoDado::where('user_id', $u->id)->where('chave', 'like', 'img.f1.%')->exists());
        $this->assertFalse(CorpoDado::where('user_id', $u->id)->where('chave', 'img.f1')->exists());
        $this->assertTrue(CorpoDado::where('user_id', $u->id)->where('chave', 'img.f10')->exists());
        $this->assertStringContainsString('"hist":{}', $this->estadoBruto(CorpoDado::class, $u->id));
    }

    public function test_mover_cartao_com_id_repetido_ganha_id_novo_e_leva_a_imagem_e_o_pdf(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $this->corpoComCartoes($u);
        Storage::disk('local')->put("editorpdf/{$u->id}/corpo/arq1.pdf", '%PDF-1.4');
        $this->gravar(DiversoDado::class, $u->id, 'estado', ['v' => 2, 'abas' => [['id' => 'quadro', 'nome' => 'Geral', 'icone' => 'pin']],
            'layout' => ['quadro' => ['tarefa-form' => ['x' => 0, 'y' => 0, 'w' => 340, 'h' => 440]]], 'blocos' => [['id' => 'mapa-inicial', 'secao' => 'quadro', 'tipo' => 'mapa']]]);

        $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'corpo', 'aba' => 'livre', 'para' => 'diverso', 'para_aba' => 'quadro'])->assertOk()->assertJson(['movidos' => 2]);

        $d = $this->estado(DiversoDado::class, $u->id);
        $this->assertCount(3, $d['blocos']);
        $novo = $d['blocos'][1];
        $this->assertSame('mapa', $novo['tipo']);
        $this->assertNotSame('mapa-inicial', $novo['id']); // o Diverso já tinha um bloco com esse id
        $this->assertSame('"m"', DiversoDado::where('user_id', $u->id)->where('chave', 'img.' . $novo['id'])->value('valor'));
        // entra abaixo dos cartões de tarefas (cartão fixo do Diverso, 440 de altura)
        $this->assertSame(456, $d['layout']['quadro'][$novo['id']]['y']);
        $this->assertTrue(Storage::disk('local')->exists("editorpdf/{$u->id}/diverso/arq1.pdf"));
        $this->assertFalse(Storage::disk('local')->exists("editorpdf/{$u->id}/corpo/arq1.pdf"));
    }

    public function test_documento_da_mente_vai_junto_e_o_destino_passa_a_criar(): void
    {
        $u = User::factory()->create();
        $this->gravar(CorpoDado::class, $u->id, 'mente.estado', ['layout' => [], 'blocos' => [
            ['id' => 'd1', 'secao' => 'livre', 'tipo' => 'documento', 'titulo' => 'Resumo'],
            ['id' => 't1', 'secao' => 'livre', 'tipo' => 'texto', 'titulo' => 'Nota'],
        ]]);
        $r = $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'mente', 'aba' => 'livre', 'para' => 'carteira'])->assertOk();
        $this->assertSame(2, $r->json('movidos'));
        $this->assertSame([], $r->json('ficaram'));
        $this->assertSame([], $this->estado(CorpoDado::class, $u->id, 'mente.estado')['blocos']);
        $cart = $this->estado(CarteiraDado::class, $u->id);
        $this->assertSame(['d1', 't1'], array_column($cart['blocos'], 'id'));
        $this->assertSame(['documento'], $cart['tiposTrazidos']);
        $this->actingAs($u)->get('/areas/carteira')->assertOk()->assertSee('js/documento-cartao.js', false);
    }

    public function test_levar_a_mente_inteira_leva_os_documentos(): void
    {
        $u = User::factory()->create();
        $this->gravar(CorpoDado::class, $u->id, 'mente.estado', ['layout' => [], 'blocos' => [
            ['id' => 'd1', 'secao' => 'estudos', 'tipo' => 'documento', 'titulo' => 'Currículo', 'links' => [], 'dados' => ['secoes' => [['id' => 's', 't' => 'Nome', 'texto' => 'Ana']]]],
        ]]);
        $this->actingAs($u)->get('/areas/diverso')->assertOk(); // grava o Diverso no formato novo
        $r = $this->actingAs($u)->postJson('/areas-config/mover-tudo', ['de' => 'mente', 'para' => 'diverso'])->assertOk();
        $this->assertSame(1, $r->json('movidos'));
        $this->assertSame([], $r->json('ficaram'));
        $d = $this->estado(DiversoDado::class, $u->id);
        $doc = collect($d['blocos'])->firstWhere('tipo', 'documento');
        $this->assertSame('Ana', $doc['dados']['secoes'][0]['texto']);
        $this->assertSame(['documento'], $d['tiposTrazidos']);
    }

    public function test_cartao_que_o_destino_nao_mostra_fica_na_origem(): void
    {
        $u = User::factory()->create();
        $this->gravar(DiversoDado::class, $u->id, 'estado', ['v' => 2, 'abas' => [], 'layout' => [], 'blocos' => [
            ['id' => 'c1', 'secao' => 'quadro', 'tipo' => 'codigo', 'titulo' => 'Repo'],
        ]]);
        $r = $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'diverso', 'aba' => 'quadro', 'ids' => ['c1'], 'para' => 'carteira'])->assertOk();
        $this->assertSame(0, $r->json('movidos'));
        $this->assertSame('Repo', $r->json('ficaram.0.titulo'));
    }

    public function test_mover_so_alguns_cartoes_e_destino_invalido(): void
    {
        $u = User::factory()->create();
        $this->corpoComCartoes($u);
        // só a foto (a pasta fica): ela sai da pasta no destino
        $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'corpo', 'aba' => 'treinos', 'ids' => ['f1'], 'para' => 'mente', 'para_aba' => 'livros'])->assertOk()->assertJson(['movidos' => 1]);
        $m = $this->estado(CorpoDado::class, $u->id, 'mente.estado');
        $this->assertSame('livros', $m['blocos'][0]['secao']);
        $this->assertArrayNotHasKey('pasta', $m['blocos'][0]);
        $this->assertTrue(CorpoDado::where('user_id', $u->id)->where('chave', 'mente.img.f1')->exists());
        $this->assertSame(['p1', 'mapa-inicial', 'pdf1'], array_column($this->estado(CorpoDado::class, $u->id)['blocos'], 'id'));

        $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'corpo', 'aba' => 'treinos', 'para' => 'negocios', 'para_aba' => 'loja'])->assertStatus(422);
        $this->actingAs($u)->postJson('/areas-config/mover', ['de' => 'corpo', 'aba' => 'treinos', 'para' => 'xnaoexiste'])->assertStatus(422);
    }

    public function test_levar_todo_o_corpo_para_uma_area_criada_e_esconder(): void
    {
        $u = User::factory()->create();
        $this->corpoComCartoes($u);
        $x = AreasConta::criarExtra($u, 'Saúde')['id'];

        $r = $this->actingAs($u->fresh())->postJson('/areas-config/mover-tudo', ['de' => 'corpo', 'para' => $x, 'esconder' => true])->assertOk();
        $this->assertSame(4, $r->json('movidos'));
        $this->assertSame(2, $r->json('abas'));

        $d = $this->estado(DiversoDado::class, $u->id); // o Diverso nunca tinha sido aberto: começa no formato novo
        $this->assertSame(2, $d['v']);
        $this->assertSame(['Corpo · Treinos', 'Corpo · Quadro livre'], array_column($d['abas'], 'nome'));
        $this->assertSame([$x, $x], array_column($d['abas'], 'grupo'));
        $this->assertCount(4, $d['blocos']);
        $this->assertSame([], $this->estado(CorpoDado::class, $u->id)['blocos']);
        $this->assertTrue(AreasConta::lista($u->fresh())['corpo']['oculta']);

        // a página da área criada mostra as abas novas; a do Diverso, não
        $this->actingAs($u->fresh())->get('/areas/extra/' . $x)->assertOk();
        $this->actingAs($u->fresh())->getJson('/areas-config/destinos')->assertOk()
            ->assertJsonFragment(['id' => $x, 'nome' => 'Saúde']);
    }

    public function test_destinos_e_mover_exigem_login(): void
    {
        $this->getJson('/areas-config/destinos')->assertUnauthorized();
        $this->postJson('/areas-config/mover', [])->assertUnauthorized();
    }
}
