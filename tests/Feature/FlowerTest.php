<?php

namespace Tests\Feature;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowerTest extends TestCase
{
    use RefreshDatabase;

    private function no(array $nos, string $id): ?array
    {
        foreach ($nos as $n) {
            if ($n['id'] === $id) return $n;
            if ($achado = $this->no($n['filhos'] ?? [], $id)) return $achado;
        }
        return null;
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/flower')->assertRedirect();
        $this->getJson('/flower/dados')->assertUnauthorized();
    }

    public function test_pagina_abre(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/flower')->assertOk()->assertSee('Meu Flower')->assertSee(route('flower.dados'), false);
    }

    public function test_conta_vazia_tem_as_cinco_petalas_com_abas_e_cartoes_fixos(): void
    {
        $u = User::factory()->create(['name' => 'Ana']);
        $r = $this->actingAs($u)->getJson('/flower/dados')->assertOk();

        $this->assertSame('Ana', $r->json('pessoa.nome'));
        $this->assertSame(['corpo', 'mente', 'carteira', 'negocios', 'diverso'], array_column($r->json('areas'), 'id'));
        $areas = $r->json('areas');
        $this->assertNotNull($this->no($areas, 'corpo:aba:treinos'));
        $this->assertNotNull($this->no($areas, 'corpo:fixo:treinos.treino-lista'));
        $this->assertNotNull($this->no($areas, 'diverso:aba:quadro'), 'Diverso sem abas ainda tem o Geral');
        $this->assertStringEndsWith('/areas/carteira?fav=cart-resumo&favsec=carteira', $this->no($areas, 'carteira:fixo:carteira.cart-resumo')['link']);
    }

    public function test_le_abas_pastas_cartoes_e_tarefas_de_cada_area(): void
    {
        $u = User::factory()->create();
        DiversoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => json_encode([
            'abas'   => [['id' => 'quadro', 'nome' => 'Geral'], ['id' => 'casa', 'nome' => 'Casa']],
            'blocos' => [
                ['id' => 'p1', 'secao' => 'casa', 'tipo' => 'pasta', 'titulo' => 'Reforma'],
                ['id' => 'p2', 'secao' => 'casa', 'tipo' => 'pasta', 'titulo' => 'Orçamentos', 'pasta' => 'p1'],
                ['id' => 'c1', 'secao' => 'casa', 'tipo' => 'tabela', 'titulo' => 'Pedreiros', 'pasta' => 'p2'],
                ['id' => 'c2', 'secao' => 'casa', 'tipo' => 'lista', 'titulo' => ''],
                ['id' => 'c3', 'secao' => 'casa', 'tipo' => 'texto', 'titulo' => 'Perdido', 'pasta' => 'naoexiste'],
            ],
        ])]);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'mente.estado', 'valor' => json_encode([
            'blocos' => [['id' => 'm1', 'secao' => 'livre', 'tipo' => 'mapa', 'titulo' => 'Ideias']],
        ])]);
        CarteiraDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => '{isto não é json']);
        Tarefa::create(['user_id' => $u->id, 'area' => 'diverso', 'titulo' => 'Comprar tinta', 'status' => 'pendente']);
        Tarefa::create(['user_id' => $u->id, 'area' => 'diverso', 'titulo' => 'Medir sala', 'status' => 'concluida']);

        $outro = User::factory()->create();
        Tarefa::create(['user_id' => $outro->id, 'area' => 'diverso', 'titulo' => 'Segredo do outro', 'status' => 'pendente']);

        $r = $this->actingAs($u)->getJson('/flower/dados')->assertOk();
        $areas = $r->json('areas');

        // pasta dentro de pasta, cartão dentro dela; cartão sem título leva o nome do tipo; pasta inexistente -> fica na aba
        $casa = $this->no($areas, 'diverso:aba:casa');
        $this->assertSame('Casa', $casa['nome']);
        $this->assertSame(['diverso:pasta:p1', 'diverso:cartao:c2', 'diverso:cartao:c3'], array_column($casa['filhos'], 'id'));
        $this->assertSame('Pedreiros', $this->no([$casa], 'diverso:pasta:p2')['filhos'][0]['nome']);
        $this->assertSame('Lista', $this->no($areas, 'diverso:cartao:c2')['nome']);
        $this->assertStringEndsWith('/areas/diverso?fav=c1&favsec=casa', $this->no($areas, 'diverso:cartao:c1')['link']);

        // Mente vem de corpo_dados "mente.estado"
        $this->assertSame('Ideias', $this->no($areas, 'mente:cartao:m1')['nome']);

        // tarefas ficam no cartão Tarefas da aba das tarefas (só as da conta)
        $lista = $this->no($areas, 'diverso:fixo:quadro.tarefa-lista');
        $this->assertSame(['Comprar tinta', 'Medir sala'], array_column($lista['filhos'], 'nome'));
        $this->assertStringNotContainsString('Segredo do outro', $r->getContent());
        $diverso = collect($areas)->firstWhere('id', 'diverso');
        $this->assertSame(2, $diverso['resumo']['tarefas']);
        $this->assertSame(1, $diverso['resumo']['feitas']);
        $this->assertSame(2, $diverso['resumo']['pastas']);

        // estado estragado não derruba o mapa
        $this->assertNotNull($this->no($areas, 'carteira:aba:invest'));
    }
}
