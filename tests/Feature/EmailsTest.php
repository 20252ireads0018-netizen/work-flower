<?php

namespace Tests\Feature;

use App\Mail\TesteEnvio;
use App\Models\EmailConta;
use App\Models\EmailMensagem;
use App\Models\EmailRegra;
use App\Models\NegocioDado;
use App\Models\User;
use App\Support\Emails\EmailErro;
use App\Support\Emails\ImapCliente;
use App\Support\Emails\LeitorEmails;
use App\Support\Emails\Mime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Negócios › E-mails: conexão (com o IMAP falso), senha criptografada, categorias, sincronização e o cliente IMAP. */
class EmailsTest extends TestCase
{
    use RefreshDatabase;

    private FakeLeitor $leitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leitor = new FakeLeitor();
        $this->app->instance(LeitorEmails::class, $this->leitor);
    }

    private function msg(string $uid, string $de, string $assunto, bool $lido = false, string $nome = ''): array
    {
        return ['uid' => $uid, 'message_id' => "m{$uid}@x", 'de_nome' => $nome ?: null, 'de_email' => $de, 'assunto' => $assunto,
            'trecho' => "texto {$uid}", 'recebido_em' => now()->subHours((int) $uid), 'lido' => $lido];
    }

    private function conectar(User $u, array $extra = [])
    {
        return $this->actingAs($u)->postJson('/negocios/emails/contas', $extra + [
            'endereco' => 'Dono@Empresa.com.br', 'senha' => 'segredo-de-teste', 'host' => 'imap.titan.email', 'porta' => 993, 'seguranca' => 'ssl',
        ]);
    }

    public function test_pagina_abre_com_regras_iniciais_e_exige_login(): void
    {
        $this->get('/negocios/emails')->assertRedirect('/login');

        $u = User::factory()->create();
        $this->actingAs($u)->get('/negocios/emails')->assertOk()->assertSee('Como funciona')->assertSee('Conectar uma caixa de e-mail')->assertSee('imap.titan.email');
        $this->assertGreaterThan(10, EmailRegra::where('user_id', $u->id)->count());
        $this->actingAs($u)->get('/areas/negocios')->assertOk()->assertSee(route('emails'), false);
    }

    public function test_conecta_guarda_senha_criptografada_e_classifica(): void
    {
        $u = User::factory()->create();
        NegocioDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => json_encode(['clientes' => [['nome' => 'Ana', 'contato' => 'ana@cliente.com / 11 9999']]])]);
        $this->leitor->novas = [
            $this->msg('1', 'cobranca@banco.com', 'Seu boleto chegou'),
            $this->msg('2', 'ana@cliente.com', 'Oi, tudo bem?'),
            $this->msg('3', 'news@loja.com', 'Só hoje: 50% de DESCONTO'),
            $this->msg('4', 'amigo@x.com', 'Churrasco sábado', true),
            $this->msg('5', 'contato@site.com', 'Pedido de orçamento'),
        ];

        $r = $this->conectar($u)->assertOk()->assertJsonPath('contas.0.endereco', 'dono@empresa.com.br');
        $this->assertStringNotContainsString('segredo-de-teste', $r->getContent());
        $this->assertArrayNotHasKey('senha', $r->json('contas.0'));
        $this->assertSame(1, $this->leitor->testes);

        $bruto = DB::table('email_contas')->value('senha');
        $this->assertNotSame('segredo-de-teste', $bruto);
        $this->assertSame('segredo-de-teste', EmailConta::first()->senha);

        $cats = EmailMensagem::pluck('categoria', 'uid');
        $this->assertSame('Financeiro', $cats['1']);
        $this->assertSame('Clientes', $cats['2']);   // está na lista de clientes
        $this->assertSame('Promoções', $cats['3']);  // sem acento e sem diferença de maiúsculas
        $this->assertSame('Outros', $cats['4']);
        $this->assertSame('Clientes', $cats['5']);
        $this->assertCount(5, $r->json('mensagens'));
        $this->assertTrue(EmailMensagem::where('uid', '4')->value('lido'));
    }

    public function test_login_recusado_nao_salva_nada(): void
    {
        $u = User::factory()->create();
        $this->leitor->falhaTeste = 'O servidor recusou o usuário ou a senha.';
        $this->conectar($u)->assertStatus(422)->assertJsonPath('erro', 'O servidor recusou o usuário ou a senha.');
        $this->assertSame(0, EmailConta::count());

        $this->conectar($u, ['host' => 'localhost'])->assertStatus(422)->assertJsonValidationErrors('host');
        $this->conectar($u, ['porta' => 22])->assertStatus(422)->assertJsonValidationErrors('porta');
    }

    public function test_limite_de_caixas_e_reconectar_a_mesma_atualiza(): void
    {
        $u = User::factory()->create();
        foreach (['a', 'b', 'c'] as $l) $this->conectar($u, ['endereco' => "{$l}@x.com"])->assertOk();
        $this->conectar($u, ['endereco' => 'd@x.com'])->assertStatus(422);
        $this->conectar($u, ['endereco' => 'a@x.com', 'senha' => 'nova'])->assertOk();
        $this->assertSame(3, EmailConta::count());
        $this->assertSame('nova', EmailConta::where('endereco', 'a@x.com')->first()->senha);
    }

    public function test_sincronizar_traz_novas_atualiza_lido_e_respeita_categoria_manual(): void
    {
        $u = User::factory()->create();
        $this->leitor->novas = [$this->msg('1', 'x@y.com', 'Fatura de outubro'), $this->msg('2', 'z@y.com', 'Olá')];
        $this->conectar($u)->assertOk();

        $m2 = EmailMensagem::where('uid', '2')->first();
        $this->actingAs($u)->patchJson("/negocios/emails/mensagens/{$m2->id}", ['categoria' => 'Clientes'])
            ->assertOk()->assertJsonPath('mensagem.manual', true);

        $this->leitor->novas = [$this->msg('3', 'w@y.com', 'Cupom para você')];
        $this->leitor->lidos = ['1' => true];
        $r = $this->actingAs($u)->postJson('/negocios/emails/sincronizar')->assertOk()->assertJsonPath('ok', '1 mensagem nova.');
        $this->assertSame(['1', '2'], $this->leitor->conhecidos);
        $this->assertCount(3, $r->json('mensagens'));
        $this->assertTrue(EmailMensagem::where('uid', '1')->value('lido'));
        $this->assertSame('Promoções', EmailMensagem::where('uid', '3')->value('categoria'));

        // regras novas reaplicam, menos na mensagem mudada à mão
        $this->actingAs($u)->putJson('/negocios/emails/regras', ['regras' => [['categoria' => 'Contas', 'campo' => 'qualquer', 'termo' => 'y.com']]])
            ->assertOk()->assertJsonPath('regras.0.categoria', 'Contas')->assertJsonFragment(['Contas']);
        $this->assertSame(['1' => 'Contas', '2' => 'Clientes', '3' => 'Contas'], EmailMensagem::orderBy('uid')->pluck('categoria', 'uid')->all());

        // de volta para o automático
        $this->actingAs($u)->patchJson("/negocios/emails/mensagens/{$m2->id}", ['categoria' => 'auto'])->assertJsonPath('mensagem.categoria', 'Contas');

        // apagar todas as regras não traz as iniciais de volta
        $this->actingAs($u)->putJson('/negocios/emails/regras', ['regras' => []])->assertOk()->assertJsonCount(0, 'regras');
        $this->actingAs($u)->get('/negocios/emails')->assertOk();
        $this->assertSame('Outros', EmailMensagem::where('uid', '1')->value('categoria'));
        $this->assertSame(1, EmailRegra::where('user_id', $u->id)->count());
    }

    public function test_falha_na_leitura_fica_registrada_na_conta(): void
    {
        $u = User::factory()->create();
        $this->conectar($u)->assertOk();
        $this->leitor->falhaLeitura = 'O servidor de e-mail demorou demais para responder.';
        $this->actingAs($u)->postJson('/negocios/emails/sincronizar')->assertOk()
            ->assertJsonPath('ok', null)
            ->assertJsonPath('erro', 'dono@empresa.com.br: O servidor de e-mail demorou demais para responder. Use “Trocar senha” na caixa para corrigir.');
        $this->assertSame('O servidor de e-mail demorou demais para responder.', EmailConta::first()->erro);
    }

    public function test_nao_mexe_na_caixa_de_outra_pessoa(): void
    {
        $dono = User::factory()->create();
        $this->leitor->novas = [$this->msg('1', 'a@b.com', 'x')];
        $this->conectar($dono)->assertOk();
        $outro = User::factory()->create();
        $conta = EmailConta::first();
        $msg = EmailMensagem::first();

        $this->actingAs($outro)->deleteJson("/negocios/emails/contas/{$conta->id}")->assertNotFound();
        $this->actingAs($outro)->patchJson("/negocios/emails/mensagens/{$msg->id}", ['categoria' => 'X'])->assertNotFound();
        $this->actingAs($outro)->getJson('/negocios/emails')->assertOk()->assertDontSee('a@b.com');

        $this->actingAs($dono)->deleteJson("/negocios/emails/contas/{$conta->id}")->assertOk()->assertJsonCount(0, 'contas');
        $this->assertSame(0, EmailMensagem::count());
    }

    public function test_comando_do_agendador_le_as_caixas(): void
    {
        $u = User::factory()->create();
        $this->conectar($u)->assertOk();
        $this->leitor->novas = [$this->msg('9', 'a@b.com', 'Nota fiscal')];
        $this->artisan('emails:sincronizar')->expectsOutputToContain('Caixas lidas: 1')->assertSuccessful();
        $this->assertSame('Financeiro', EmailMensagem::where('uid', '9')->value('categoria'));
    }

    public function test_gmail_pelo_google_fica_desligado_sem_chaves_e_funciona_com_elas(): void
    {
        $u = User::factory()->create();
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->actingAs($u)->get('/negocios/emails')->assertDontSee('Entrar com Google');
        $this->actingAs($u)->get('/negocios/emails/google')->assertRedirect(route('emails'));

        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'seg', 'services.google.gmail_publico' => true]);
        $ida = $this->actingAs($u)->get('/negocios/emails/google');
        $this->assertStringStartsWith('https://accounts.google.com/', $ida->headers->get('Location'));
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 3600]),
            'gmail.googleapis.com/*/profile' => Http::response(['emailAddress' => 'Eu@Gmail.com']),
        ]);
        $this->leitor->novas = [$this->msg('g1', 'a@b.com', 'Recibo')];
        $this->actingAs($u)->get('/negocios/emails/google/retorno?state=' . $q['state'] . '&code=abc')->assertRedirect(route('emails'));

        $c = EmailConta::first();
        $this->assertSame(['gmail', 'eu@gmail.com', 'ref'], [$c->tipo, $c->endereco, $c->refresh_token]);
        $this->assertSame(1, EmailMensagem::count());

        // o mesmo state não serve duas vezes
        $this->actingAs($u)->get('/negocios/emails/google/retorno?state=' . $q['state'] . '&code=abc')->assertSessionHas('emails_erro');
    }

    public function test_corpo_em_base64_passa_pelo_mod_security(): void
    {
        $u = User::factory()->create();
        $corpo = ['endereco' => 'a@x.com', 'senha' => "x'; DROP TABLE users; --", 'host' => 'imap.titan.email', 'porta' => 993, 'seguranca' => 'ssl'];
        $this->actingAs($u)->postJson('/negocios/emails/contas', ['b64' => base64_encode(json_encode($corpo))])->assertOk();
        $this->assertSame("x'; DROP TABLE users; --", EmailConta::first()->senha);

        $regras = ['regras' => [['categoria' => 'Código', 'campo' => 'assunto', 'termo' => '<script>select * from']]];
        $this->actingAs($u)->putJson('/negocios/emails/regras', ['b64' => base64_encode(json_encode($regras))])
            ->assertOk()->assertJsonPath('regras.0.termo', '<script>select * from');
        $this->actingAs($u)->postJson('/negocios/emails/contas', ['b64' => '%%%'])->assertStatus(422);
    }

    public function test_envio_de_teste_so_para_admin_com_mail_fake_e_batimento_do_cron(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.titan.email', 'mail.mailers.smtp.port' => 465, 'mail.mailers.smtp.scheme' => 'smtps']);
        $comum = User::factory()->create();
        $this->actingAs($comum)->get('/negocios/emails')->assertOk()->assertDontSee('Testar envio do site');
        $this->actingAs($comum)->postJson('/negocios/emails/teste-envio')->assertNotFound();
        Mail::assertNothingSent();

        $admin = User::factory()->create(['email' => User::ADMINS[0]]);
        $this->actingAs($admin)->get('/negocios/emails')->assertOk()->assertSee('Testar envio do site')->assertSee('smtp.titan.email');
        $this->actingAs($admin)->postJson('/negocios/emails/teste-envio')->assertOk()
            ->assertJsonPath('envio.host', 'smtp.titan.email')->assertJsonPath('envio.calendario', null)
            ->assertJsonFragment(['ok' => 'E-mail de teste enviado para ' . User::ADMINS[0] . ' por smtp.titan.email:465. Confira a caixa de entrada e o spam.']);
        Mail::assertSent(TesteEnvio::class, fn ($m) => $m->hasTo(User::ADMINS[0]));
        $this->assertStringContainsString('e-mail de teste do Work Flower', (new TesteEnvio('Ana', 'agora'))->render());

        // o comando dos lembretes grava quando rodou
        $this->artisan('calendario:lembrar')->assertSuccessful();
        $this->actingAs($admin)->postJson('/negocios/emails/teste-envio')->assertOk()->assertJsonPath('envio.calendario', fn ($v) => is_string($v) && $v !== '');
    }

    public function test_envio_de_teste_mostra_erro_do_smtp_sem_senha(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'dono@empresa.com', 'mail.mailers.smtp.password' => 'Segredo123']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Failed to authenticate on SMTP server with username "dono@empresa.com" using password Segredo123: 535 Authentication failed'));
        $admin = User::factory()->create(['email' => User::ADMINS[0]]);
        $r = $this->actingAs($admin)->postJson('/negocios/emails/teste-envio')->assertStatus(422);
        $this->assertStringContainsString('535 Authentication failed', $r->json('erro'));
        $this->assertStringNotContainsString('Segredo123', $r->getContent());
        $this->assertStringNotContainsString('dono@empresa.com', $r->getContent());
    }

    public function test_envio_simulado_quando_mailer_e_log(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $admin = User::factory()->create(['email' => User::ADMINS[0]]);
        $this->actingAs($admin)->postJson('/negocios/emails/teste-envio')->assertOk()
            ->assertJsonPath('envio.so_log', true)->assertJson(fn ($j) => $j->where('ok', fn ($v) => str_contains($v, 'MAIL_MAILER=log'))->etc());
    }

    /* ---------------- partes sem rede ---------------- */

    public function test_mime_decodifica_assunto_remetente_e_trecho(): void
    {
        $this->assertSame('Promoção relâmpago', Mime::decodificar('=?UTF-8?B?UHJvbW/Dp8Ojbw==?= =?UTF-8?Q?_rel=C3=A2mpago?='));
        $this->assertSame(['João Silva', 'joao@x.com'], Mime::remetente('"=?ISO-8859-1?Q?Jo=E3o_Silva?=" <Joao@X.com>'));
        $this->assertSame(['', 'so@email.com'], Mime::remetente('so@email.com'));

        $corpo = "--b1\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>só html</p>\r\n"
            . "--b1\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode('Olá, segue o orçamento pedido.')) . "--b1--";
        $this->assertSame('Olá, segue o orçamento pedido.', Mime::trecho('multipart/alternative; boundary="b1"', '', $corpo));
        $this->assertSame('Preço: R$ 10', Mime::trecho('text/plain; charset=iso-8859-1', 'quoted-printable', "Pre=E7o: R$ 10"));
        $this->assertSame('Título Oi', Mime::trecho('text/html', '', '<style>x{}</style><h1>T&iacute;tulo</h1><p>Oi</p>'));
    }

    public function test_cliente_imap_le_fetch_com_literais_sem_marcar_como_lido(): void
    {
        [$cliente, $servidor] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $cab = "From: =?UTF-8?Q?Loja_Bacana?= <ofertas@loja.com>\r\nSubject: Cupom de 10%\r\nContent-Type: text/plain\r\n\r\n";
        $txt = "Use o cupom BEMVINDO.\r\n";
        fwrite($servidor, "W1 OK LOGIN completed\r\n"
            . "* FLAGS (\\Seen)\r\n* OK [UIDVALIDITY 777] ok\r\nW2 OK [READ-ONLY] EXAMINE completed\r\n"
            . "* SEARCH 41 42\r\nW3 OK SEARCH done\r\n"
            . '* 1 FETCH (UID 42 FLAGS (\\Seen) INTERNALDATE "03-Nov-2026 10:00:00 -0300" BODY[HEADER.FIELDS (FROM SUBJECT)] {' . strlen($cab) . "}\r\n" . $cab
            . ' BODY[TEXT]<0> {' . strlen($txt) . "}\r\n" . $txt . ")\r\n"
            . "* 2 FETCH (UID 41 FLAGS () INTERNALDATE \"02-Nov-2026 09:00:00 +0000\" BODY[HEADER.FIELDS (FROM SUBJECT)] \"\" BODY[TEXT]<0> NIL)\r\n"
            . "W4 OK FETCH done\r\n");

        $imap = new ImapCliente(5);
        $imap->usarConexao($cliente);
        $imap->entrar('eu@x.com', 'se"nha');
        $this->assertSame('777', $imap->examinar());
        $this->assertSame([41, 42], $imap->buscarDesde(now()));
        $lista = $imap->cabecalhos([41, 42]);

        $this->assertCount(2, $lista);
        $this->assertSame(42, $lista[0]['uid']);
        $this->assertTrue($lista[0]['lido']);
        $this->assertSame($cab, $lista[0]['cabecalho']);
        $this->assertSame($txt, $lista[0]['corpo']);
        $this->assertFalse($lista[1]['lido']);
        $this->assertSame('', $lista[1]['corpo']);

        $enviado = fread($servidor, 8192);
        $this->assertStringContainsString('W1 LOGIN "eu@x.com" "se\\"nha"', $enviado);
        $this->assertStringContainsString('W2 EXAMINE "INBOX"', $enviado);
        $this->assertStringContainsString('BODY.PEEK[TEXT]', $enviado);
        $this->assertStringNotContainsString('STORE', $enviado);
    }

    public function test_cliente_imap_recusa_servidor_interno(): void
    {
        $this->expectException(EmailErro::class);
        ImapCliente::ipPublico('localhost.localdomain');
    }
}

/** IMAP falso: devolve o que o teste mandar e guarda o que recebeu. */
class FakeLeitor implements LeitorEmails
{
    public array $novas = [];
    public array $lidos = [];
    public array $conhecidos = [];
    public int $testes = 0;
    public ?string $falhaTeste = null;
    public ?string $falhaLeitura = null;

    public function testar(\App\Models\EmailConta $conta): void
    {
        $this->testes++;
        if ($this->falhaTeste) throw new EmailErro($this->falhaTeste);
    }

    public function ler(\App\Models\EmailConta $conta, array $conhecidos, int $dias, int $limite): array
    {
        if ($this->falhaLeitura) throw new EmailErro($this->falhaLeitura);
        $this->conhecidos = $conhecidos;
        sort($this->conhecidos);
        $novas = array_values(array_filter($this->novas, fn ($m) => ! in_array($m['uid'], $conhecidos, true)));

        return ['uidvalidity' => '1', 'novas' => $novas, 'lidos' => $this->lidos];
    }
}
