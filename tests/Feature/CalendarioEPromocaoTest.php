<?php

namespace Tests\Feature;

use App\Mail\LembreteEvento;
use App\Models\Evento;
use App\Models\PixPagamento;
use App\Models\Promocao;
use App\Models\User;
use App\Support\Calendario;
use App\Support\Pix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Calendário do painel (eventos, repetição, lembretes) e promoções relâmpago dos planos. */
class CalendarioEPromocaoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => User::ADMINS[0]]);
    }

    private function planos(): void
    {
        DB::table('plans')->insert([
            ['name' => 'Gratuito', 'slug' => 'gratuito', 'price' => 0, 'active' => 1, 'sort' => 1],
            ['name' => 'Básico', 'slug' => 'basico', 'price' => 19.90, 'active' => 1, 'sort' => 2],
        ]);
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'loja@exemplo.com', 'nome' => 'Joao', 'cidade' => 'Sao Paulo']);
    }

    public function test_evento_semanal_aparece_em_todas_as_semanas_e_so_para_o_dono(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/calendario/eventos', [
            'titulo' => 'Aula', 'inicio' => '2026-11-02T19:00', 'fim' => '2026-11-02T21:00', 'repete' => 'semanal',
            'repete_ate' => '2026-11-23', 'lembrete_min' => 30, 'lembrar_email' => true, 'cor' => '#F472B6',
        ])->assertOk()->assertJsonPath('evento.cor', '#f472b6');

        $r = $this->actingAs($u)->getJson('/calendario?de=2026-11-01&ate=2026-11-30')->assertOk();
        $this->assertSame(['2026-11-02T19:00', '2026-11-09T19:00', '2026-11-16T19:00', '2026-11-23T19:00'], collect($r->json('eventos'))->pluck('ocorrencia')->all());
        $r->assertJsonPath('eventos.1.ocorrencia_fim', '2026-11-09T21:00');

        $outro = User::factory()->create();
        $this->actingAs($outro)->getJson('/calendario?de=2026-11-01&ate=2026-11-30')->assertOk()->assertJsonCount(0, 'eventos');
        $id = Evento::first()->id;
        $this->actingAs($outro)->putJson("/calendario/eventos/{$id}", ['titulo' => 'x', 'inicio' => '2026-11-02T19:00', 'repete' => 'nao'])->assertNotFound();
        $this->actingAs($outro)->deleteJson("/calendario/eventos/{$id}")->assertNotFound();
        $this->assertSame(1, Evento::count());
    }

    public function test_repeticao_mensal_pula_meses_sem_o_dia_e_anual_respeita_bissexto(): void
    {
        $u = User::factory()->create();
        $mensal = new Evento(['titulo' => 'Fatura', 'inicio' => Carbon::parse('2026-01-31 08:00'), 'repete' => 'mensal']);
        $dias = array_map(fn ($d) => $d->format('m-d'), Calendario::ocorrencias($mensal, Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30 23:59')));
        $this->assertSame(['01-31', '03-31', '05-31'], $dias);

        $anual = new Evento(['titulo' => 'Niver', 'inicio' => Carbon::parse('2024-02-29 00:00'), 'repete' => 'anual', 'dia_todo' => true]);
        $anos = array_map(fn ($d) => $d->format('Y-m-d'), Calendario::ocorrencias($anual, Carbon::parse('2025-01-01'), Carbon::parse('2028-12-31 23:59')));
        $this->assertSame(['2028-02-29'], $anos);

        $diario = new Evento(['titulo' => 'Água', 'inicio' => Carbon::parse('2026-01-01 07:00'), 'repete' => 'diario']);
        $this->assertCount(3, Calendario::ocorrencias($diario, Carbon::parse('2026-11-04'), Carbon::parse('2026-11-06 23:59')));
    }

    public function test_tarefas_com_prazo_aparecem_no_calendario(): void
    {
        $u = User::factory()->create();
        $u->tarefas()->create(['area' => 'mente', 'titulo' => 'Ler capítulo', 'status' => 'pendente', 'prazo' => '2026-11-10']);

        $this->actingAs($u)->getJson('/calendario?de=2026-11-01&ate=2026-11-30')->assertOk()
            ->assertJsonPath('tarefas.0.titulo', 'Ler capítulo')->assertJsonPath('tarefas.0.prazo', '2026-11-10');
    }

    public function test_lembrete_por_email_sai_uma_vez_na_hora_certa(): void
    {
        Mail::fake();
        $u = User::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00', Calendario::FUSO)); // 12:00 em Brasília
        Evento::forceCreate(['user_id' => $u->id, 'titulo' => 'Dentista', 'inicio' => '2026-11-04 12:10:00', 'repete' => 'nao', 'lembrete_min' => 15, 'lembrar_email' => true]);
        Evento::forceCreate(['user_id' => $u->id, 'titulo' => 'Sem e-mail', 'inicio' => '2026-11-04 12:10:00', 'repete' => 'nao', 'lembrete_min' => 15, 'lembrar_email' => false]);
        Evento::forceCreate(['user_id' => $u->id, 'titulo' => 'Mais tarde', 'inicio' => '2026-11-04 18:00:00', 'repete' => 'nao', 'lembrete_min' => 15, 'lembrar_email' => true]);

        $this->artisan('calendario:lembrar')->assertSuccessful();
        Mail::assertSent(LembreteEvento::class, 1);
        Mail::assertSent(LembreteEvento::class, fn ($m) => $m->evento->titulo === 'Dentista' && $m->hasTo($u->email));

        $this->artisan('calendario:lembrar')->assertSuccessful(); // minuto seguinte: não repete
        Mail::assertSent(LembreteEvento::class, 1);

        // o navegador recebe os lembretes com o fuso certo
        $l = collect($this->actingAs($u)->getJson('/calendario/lembretes')->assertOk()->json('lembretes'))->keyBy('titulo');
        $this->assertSame('2026-11-04T11:55:00-03:00', $l['Dentista']['avisar']);
        $this->assertSame('2026-11-04T17:45:00-03:00', $l['Mais tarde']['avisar']);
        $this->assertTrue($l->has('Sem e-mail')); // aviso no navegador vale mesmo sem e-mail
    }

    public function test_promocao_muda_o_preco_do_pix_e_some_quando_acaba(): void
    {
        $this->planos();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/promocoes', [
            'plano_slug' => 'basico', 'tipo' => 'desconto', 'valor' => 50, 'termina_em' => now(Calendario::FUSO)->addHour()->format('Y-m-d\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('9.95', (string) Promocao::first()->preco);

        $u = User::factory()->create();
        $this->actingAs($u)->get('/planos')->assertOk()->assertSee(',95', false)->assertSee('data-promo-fim', false);
        $this->actingAs($u)->get('/assinar/basico')->assertRedirect();
        $this->assertSame('9.95', (string) PixPagamento::where('user_id', $u->id)->value('valor'));

        Promocao::first()->forceFill(['termina_em' => now()->subMinute()])->save();
        $outro = User::factory()->create();
        $this->actingAs($outro)->get('/planos')->assertOk()->assertDontSee('data-promo-fim', false);
        $this->actingAs($outro)->get('/assinar/basico')->assertRedirect();
        $this->assertSame('19.90', (string) PixPagamento::where('user_id', $outro->id)->value('valor'));
    }

    public function test_promocao_so_para_admin_e_com_preco_valido(): void
    {
        $this->planos();
        $u = User::factory()->create();
        $this->actingAs($u)->get('/admin/promocoes')->assertNotFound();
        $this->actingAs($u)->post('/admin/promocoes', ['plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => 1, 'termina_em' => '2030-01-01T10:00'])->assertNotFound();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/promocoes')->assertOk()->assertSee('Nova promoção');
        $this->actingAs($admin)->post('/admin/promocoes', ['plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => 25, 'termina_em' => '2030-01-01T10:00'])
            ->assertSessionHasErrors('valor');
        $this->actingAs($admin)->post('/admin/promocoes', ['plano_slug' => 'gratuito', 'tipo' => 'preco', 'valor' => 1, 'termina_em' => '2030-01-01T10:00'])
            ->assertSessionHasErrors('plano_slug');
        $this->actingAs($admin)->post('/admin/promocoes', ['plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => 5, 'termina_em' => '2020-01-01T10:00'])
            ->assertSessionHasErrors('termina_em');
        $this->assertSame(0, Promocao::count());

        $this->actingAs($admin)->post('/admin/promocoes', ['plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => '4.90', 'termina_em' => '2030-01-01T10:00'])->assertSessionHasNoErrors();
        $p = Promocao::first();
        $this->assertTrue($p->vigente());
        $this->actingAs($admin)->post("/admin/promocoes/{$p->id}/encerrar")->assertRedirect();
        $this->assertFalse($p->fresh()->vigente());
    }
}
