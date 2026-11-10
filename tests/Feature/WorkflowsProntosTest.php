<?php

namespace Tests\Feature;

use App\Models\CorpoDado;
use App\Models\DiversoDado;
use App\Models\User;
use App\Support\Ambiente;
use App\Support\AreasConta;
use App\Support\FundoTema;
use App\Support\WorkflowsProntos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Workflows prontos": área temática com abas, cartões e ambiente criada em um clique (App\Support\WorkflowsProntos). */
class WorkflowsProntosTest extends TestCase
{
    use RefreshDatabase;

    private function estado(int $uid): array
    {
        return json_decode(DiversoDado::where('user_id', $uid)->where('chave', 'estado')->value('valor'), true);
    }

    public function test_painel_mostra_a_galeria_com_todos_os_temas(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->get('/painel')->assertOk()->assertSee('Workflows prontos');
        foreach (['Estudos', 'Projetos', 'Game dev', 'Negócios', 'Music production', 'Leitura', 'Fitness', 'Viagem', 'Escrita e worldbuilding'] as $nome) {
            $r->assertSee($nome);
        }
        $this->assertGreaterThanOrEqual(9, count(WorkflowsProntos::temas()));
    }

    public function test_cria_area_com_abas_cartoes_e_ambiente_em_um_clique(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->post(route('workflows.prontos.criar', 'estudos'));

        $area = AreasConta::config($u->fresh())['extras'][0];
        $this->assertSame('Estudos', $area['nome']);
        $r->assertRedirect(route('areas.extra', $area['id']));

        $e = $this->estado($u->id);
        $this->assertSame(2, $e['v']);
        $abas = array_values(array_filter($e['abas'], fn ($a) => ($a['grupo'] ?? null) === $area['id']));
        $this->assertSame(['Planejamento', 'Revisões', 'Provas'], array_column($abas, 'nome'));
        $this->assertSame('calendario', $abas[0]['icone']);

        // cartões de cada aba, com posição em colunas (mural) e cor
        $doPlan = array_values(array_filter($e['blocos'], fn ($b) => $b['secao'] === $abas[0]['id']));
        $this->assertSame(['texto', 'tabela', 'lista', 'texto'], array_column($doPlan, 'tipo'));
        $this->assertSame('Revisar anotações da aula', $doPlan[2]['dados']['itens'][0]['t']);
        $this->assertFalse($doPlan[2]['dados']['itens'][0]['f']);
        $this->assertSame(['Dia', 'Matéria', 'Tempo'], $doPlan[1]['dados']['cab']);
        $lay = $e['layout'][$abas[0]['id']];
        $this->assertCount(4, $lay);
        $this->assertSame([0, 356, 712], array_values(array_unique(array_column(array_slice($lay, 0, 3), 'x'))));
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $lay[$doPlan[0]['id']]['cor']);

        // ambiente de cada aba: fundo próprio com imagem desenhada e a rádio do YouTube
        foreach ($abas as $a) {
            $amb = Ambiente::opcoes($u->id, 'diverso', $a['id']);
            $this->assertTrue($amb['fundo']['proprio']);
            $this->assertSame('youtube', $amb['musica']['tipo']);
            $this->assertSame('lTRiuFIWV54', $amb['musica']['youtube']);
            if (function_exists('imagecreatetruecolor')) {
                $img = CorpoDado::where('user_id', $u->id)->where('chave', Ambiente::chave('diverso', $a['id']) . '.img')->value('valor');
                $this->assertStringStartsWith('data:image/jpeg;base64,', $img);
            }
        }

        // a área abre com as abas e o ambiente
        $this->actingAs($u)->get(route('areas.extra', $area['id']))->assertOk()->assertSee('Planejamento')->assertSee('lTRiuFIWV54');
        if (function_exists('imagecreatetruecolor')) {
            $this->actingAs($u)->get(route('ambiente.imagem', 'diverso') . '?setor=' . $abas[0]['id'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        }
    }

    public function test_todos_os_temas_sao_criados_e_preservam_o_diverso(): void
    {
        $u = User::factory()->create();
        DiversoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => json_encode([
            'v' => 2, 'abas' => [['id' => 'minha', 'nome' => 'Minha aba', 'icone' => 'pasta']],
            'blocos' => [['id' => 'b1', 'secao' => 'minha', 'tipo' => 'texto', 'titulo' => 'Meu', 'links' => [], 'dados' => ['texto' => 'oi']]],
            'layout' => ['minha' => ['b1' => ['x' => 0, 'y' => 0, 'w' => 340, 'h' => 200, 'z' => 1]]],
        ])]);

        foreach (array_keys(WorkflowsProntos::temas()) as $tema) {
            $this->actingAs($u)->post(route('workflows.prontos.criar', $tema))->assertRedirect()->assertSessionMissing('erro');
        }
        $extras = AreasConta::config($u->fresh())['extras'];
        $this->assertCount(count(WorkflowsProntos::temas()), $extras);

        $e = $this->estado($u->id);
        $this->assertSame('Minha aba', $e['abas'][0]['nome']);
        $this->assertSame('oi', $e['blocos'][0]['dados']['texto']);
        $ids = array_column($e['blocos'], 'id');
        $this->assertSame(count($ids), count(array_unique($ids)));
        // toda aba do tema pertence a uma área criada; todo cartão tem posição
        foreach ($e['blocos'] as $b) {
            $this->assertArrayHasKey($b['id'], $e['layout'][$b['secao']]);
        }
    }

    public function test_mesmo_tema_duas_vezes_ganha_nome_novo_e_tema_desconhecido_falha(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('workflows.prontos.criar', 'gamedev'));
        $this->actingAs($u)->post(route('workflows.prontos.criar', 'gamedev'));
        $this->assertSame(['Game dev', 'Game dev 2'], array_column(AreasConta::config($u->fresh())['extras'], 'nome'));

        $this->actingAs($u)->from('/painel')->post(route('workflows.prontos.criar', 'naoexiste'))->assertRedirect('/painel')->assertSessionHas('erro');
        $this->actingAs($u)->postJson(route('workflows.prontos.criar', 'naoexiste'))->assertStatus(422);
        $this->assertCount(2, AreasConta::config($u->fresh())['extras']);
    }

    public function test_diverso_no_formato_antigo_nao_e_estragado(): void
    {
        $u = User::factory()->create();
        DiversoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => '{"blocos":[],"layout":{}}']);

        $this->actingAs($u)->from('/painel')->post(route('workflows.prontos.criar', 'fitness'))->assertSessionHas('erro');
        $this->assertSame('{"blocos":[],"layout":{}}', DiversoDado::where('user_id', $u->id)->value('valor'));
        $this->assertSame([], AreasConta::config($u->fresh())['extras']);
    }

    public function test_fundo_do_tema_e_uma_imagem_valida(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD não instalado.');
        }
        foreach (FundoTema::PADROES as $p) {
            $url = FundoTema::gerar(['#1e1b4b', '#7c3aed', '#ec4899'], $p);
            $info = getimagesizefromstring(base64_decode(substr($url, strlen('data:image/jpeg;base64,'))));
            $this->assertSame([1280, 800, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
            $this->assertLessThan(400000, strlen($url));
        }
    }

    public function test_cada_tema_tem_ilustracao_propria_e_vai_para_o_fundo(): void
    {
        $vistas = [];
        foreach (array_keys(WorkflowsProntos::temas()) as $tema) {
            $url = WorkflowsProntos::ilustracao($tema);
            $this->assertNotNull($url, "sem ilustração: $tema");
            $info = getimagesizefromstring(base64_decode(substr($url, strlen('data:image/jpeg;base64,'))));
            $this->assertSame([1600, 1000, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
            $this->assertFileExists(resource_path("workflows-prontos/{$tema}.svg"));
            $this->assertFileExists(public_path("img/workflows-prontos/{$tema}.jpg"));
            $vistas[] = md5($url);
        }
        $this->assertSame(count($vistas), count(array_unique($vistas))); // nenhuma repetida
        $this->assertNull(WorkflowsProntos::ilustracao('../env'));

        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('img/workflows-prontos/projetos.jpg', false)->assertSee('Onda japonesa');
        $this->actingAs($u)->post(route('workflows.prontos.criar', 'projetos'));
        $e = $this->estado($u->id);
        $aba = $e['abas'][count($e['abas']) - 1]['id'];
        $this->assertSame(WorkflowsProntos::ilustracao('projetos'),
            CorpoDado::where('user_id', $u->id)->where('chave', Ambiente::chave('diverso', $aba) . '.img')->value('valor'));
    }
}
