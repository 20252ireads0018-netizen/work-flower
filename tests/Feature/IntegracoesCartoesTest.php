<?php

namespace Tests\Feature;

use App\Mail\LembreteEvento;
use App\Models\ArmazenamentoBackup;
use App\Models\ArmazenamentoDestino;
use App\Models\EmailConta;
use App\Models\EmailMensagem;
use App\Models\Evento;
use App\Models\GoogleConexao;
use App\Models\User;
use App\Support\Google\Youtube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cartões Google Drive, Gmail, YouTube e Lembretes por e-mail do quadro (public/js/integracoes-cartoes.js). */
class IntegracoesCartoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'armazenamento.google.client_id' => 'cliente.apps.googleusercontent.com', 'armazenamento.google.client_secret' => 'segredo',
            'services.google.client_id' => 'cliente.apps.googleusercontent.com', 'services.google.client_secret' => 'segredo',
            'services.google.relay' => '', 'services.google.redirect' => null,
            'services.google.publico' => true, 'services.google.gmail_publico' => true,
        ]);
    }

    private function drive(User $u): ArmazenamentoDestino
    {
        return ArmazenamentoDestino::create([
            'user_id' => $u->id, 'tipo' => 'gdrive', 'nome' => 'Google Drive', 'resumo' => 'x',
            'credenciais' => ['access_token' => 'ya29.t', 'refresh_token' => '1//r', 'expira_em' => time() + 3600, 'email' => 'pessoa@gmail.com', 'pasta_id' => 'pasta-wf'],
        ]);
    }

    // ------------------------------------------------------------------ quadro

    public function test_quadro_carrega_os_cartoes_novos(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/areas/diverso')->assertOk()->getContent();
        $this->assertStringContainsString('js/integracoes-cartoes.js', $html);
        $this->assertStringContainsString("b.tipo === 'gdrive'", $html);
        $this->assertStringContainsString("b.tipo === 'lembretes'", $html);
        $this->assertStringContainsString('data-drive="' . route('google.drive') . '"', $html);
    }

    // ------------------------------------------------------------------ Google Drive

    public function test_drive_sem_conexao_mostra_o_botao_de_conectar(): void
    {
        $this->get('/google/drive')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->getJson('/google/drive')
            ->assertOk()->assertJson(['conectado' => false, 'conectar' => route('armazenamento.google')]);
    }

    public function test_drive_lista_traz_envia_e_faz_backup(): void
    {
        $u = User::factory()->create();
        $d = $this->drive($u);
        Http::fake([
            'www.googleapis.com/drive/v3/files/arq1?*' => Http::response('Linha 1' . "\n" . 'Ação'),
            'www.googleapis.com/drive/v3/files/arq1*' => Http::response(['id' => 'arq1', 'name' => 'notas.md', 'mimeType' => 'text/markdown', 'size' => '12', 'webViewLink' => 'https://drive/x']),
            'www.googleapis.com/drive/v3/files/doc1/export*' => Http::response("a,b\n1,2"),
            'www.googleapis.com/drive/v3/files/doc1*' => Http::response(['id' => 'doc1', 'name' => 'Planilha', 'mimeType' => 'application/vnd.google-apps.spreadsheet']),
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => [['id' => 'arq1', 'name' => 'notas.md', 'mimeType' => 'text/markdown', 'size' => '12', 'modifiedTime' => '2026-11-10T10:00:00Z']]]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'novo1']),
        ]);

        $this->actingAs($u)->getJson('/google/drive?q=no')->assertOk()
            ->assertJson(['conectado' => true, 'email' => 'pessoa@gmail.com', 'arquivos' => [['id' => 'arq1', 'nome' => 'notas.md']]]);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), "'pasta-wf' in parents") && str_contains(urldecode($r->url()), "name contains 'no'"));

        $this->actingAs($u)->postJson('/google/drive/arquivos', ['nome' => 'Minha/nota.md', 'mime' => 'text/markdown', 'b64' => base64_encode("# Título\nAção")])
            ->assertOk()->assertJson(['ok' => true, 'id' => 'novo1']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=multipart') && str_contains($r->body(), '"name":"Minha-nota.md"')
            && str_contains($r->body(), "# Título\nAção"));

        $this->actingAs($u)->patchJson('/google/drive/diario', ['ligado' => true])->assertOk()->assertJson(['backup_diario' => true]);
        $this->assertTrue($d->fresh()->backup_diario);

        $this->actingAs($u)->postJson('/google/drive/backup')->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, ArmazenamentoBackup::where('destino_id', $d->id)->count());

        // sem Drive conectado, as ações respondem 409
        $this->actingAs(User::factory()->create())->postJson('/google/drive/backup')->assertStatus(409);
    }

    public function test_drive_converte_arquivos_para_cartao(): void
    {
        $u = User::factory()->create();
        $this->drive($u);
        Http::fake(function (HttpRequest $r) {
            $url = urldecode($r->url());
            if (str_contains($url, 'files/planilha123/export')) return Http::response("nome;valor\nA;1");
            if (str_contains($url, 'files/planilha123')) return Http::response(['id' => 'planilha123', 'name' => 'Contas', 'mimeType' => 'application/vnd.google-apps.spreadsheet']);
            if (str_contains($url, 'files/texto12345') && str_contains($url, 'alt=media')) return Http::response("Olá\nmundo");
            if (str_contains($url, 'files/texto12345')) return Http::response(['id' => 'texto12345', 'name' => 'nota.txt', 'mimeType' => 'text/plain', 'size' => '10']);
            if (str_contains($url, 'files/imagem1234') && str_contains($url, 'alt=media')) return Http::response("\x89PNGabc");
            if (str_contains($url, 'files/imagem1234')) return Http::response(['id' => 'imagem1234', 'name' => 'foto.png', 'mimeType' => 'image/png', 'size' => '7']);
            if (str_contains($url, 'files/video12345')) return Http::response(['id' => 'video12345', 'name' => 'aula.mp4', 'mimeType' => 'video/mp4', 'size' => '999', 'webViewLink' => 'https://drive/v']);

            return Http::response([], 404);
        });

        $this->actingAs($u)->getJson('/google/drive/arquivos/planilha123')->assertOk()->assertJson(['tipo' => 'csv', 'conteudo' => "nome;valor\nA;1"]);
        $this->actingAs($u)->getJson('/google/drive/arquivos/texto12345')->assertOk()->assertJson(['tipo' => 'texto', 'conteudo' => "Olá\nmundo"]);
        $this->actingAs($u)->getJson('/google/drive/arquivos/imagem1234')->assertOk()->assertJson(['tipo' => 'imagem', 'src' => 'data:image/png;base64,' . base64_encode("\x89PNGabc")]);
        $this->actingAs($u)->getJson('/google/drive/arquivos/video12345')->assertOk()->assertJson(['tipo' => 'link', 'link' => 'https://drive/v']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'video12345') && str_contains($r->url(), 'alt=media'));
    }

    // ------------------------------------------------------------------ Gmail

    public function test_gmail_cartao_lista_filtra_e_abre_a_mensagem_inteira(): void
    {
        $u = User::factory()->create();
        $conta = EmailConta::create(['user_id' => $u->id, 'tipo' => 'gmail', 'endereco' => 'pessoa@gmail.com', 'host' => 'gmail.googleapis.com',
            'access_token' => 'ya29.g', 'refresh_token' => '1//g', 'token_expira_em' => now()->addHour()]);
        $base = ['user_id' => $u->id, 'conta_id' => $conta->id, 'recebido_em' => now(), 'lido' => false, 'categoria' => 'Clientes'];
        $m1 = EmailMensagem::create($base + ['uid' => 'g1', 'de_email' => 'cliente@loja.com', 'assunto' => 'Orçamento do site', 'trecho' => 'Oi']);
        EmailMensagem::create(['categoria' => 'Promoções', 'uid' => 'g2', 'de_email' => 'news@x.com', 'assunto' => 'Oferta'] + $base);

        $this->actingAs($u)->getJson('/negocios/emails/cartao')->assertOk()
            ->assertJsonCount(2, 'mensagens')->assertJsonPath('contas.0.endereco', 'pessoa@gmail.com')
            ->assertJsonPath('google', route('emails.google', ['de' => 'cartao']));
        $this->actingAs($u)->getJson('/negocios/emails/cartao?q=orçamento')->assertJsonCount(1, 'mensagens')->assertJsonPath('mensagens.0.id', $m1->id);
        $this->actingAs($u)->getJson('/negocios/emails/cartao?categoria=Promoções')->assertJsonCount(1, 'mensagens')->assertJsonPath('mensagens.0.assunto', 'Oferta');

        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        Http::fake(['gmail.googleapis.com/gmail/v1/users/me/messages/g1*' => Http::response(['payload' => ['mimeType' => 'multipart/alternative', 'parts' => [
            ['mimeType' => 'text/html', 'body' => ['data' => $b64('<p>HTML</p>')]],
            ['mimeType' => 'text/plain', 'body' => ['data' => $b64("Olá,\n\nsegue o orçamento.\n\nAbraço")]],
        ]]])]);
        $this->actingAs($u)->getJson("/negocios/emails/mensagens/{$m1->id}/corpo")->assertOk()->assertJson(['texto' => "Olá,\n\nsegue o orçamento.\n\nAbraço"]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'format=full') && $r->header('Authorization')[0] === 'Bearer ya29.g');

        // mensagem de outra pessoa
        $this->actingAs(User::factory()->create())->getJson("/negocios/emails/mensagens/{$m1->id}/corpo")->assertNotFound();
    }

    public function test_gmail_pelo_cartao_volta_para_o_quadro(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->from(url('/areas/diverso'))->get('/negocios/emails/google?de=cartao')->assertRedirectContains('accounts.google.com');
        $this->assertSame(url('/areas/diverso'), session('emails_voltar'));
    }

    // ------------------------------------------------------------------ YouTube

    public function test_youtube_conecta_pelo_google_e_lista_o_canal(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/youtube/cartao')->assertOk()->assertJson(['conectado' => false, 'pode_conectar' => true, 'conectar' => route('youtube.google')]);

        $ida = $this->actingAs($u)->from(url('/areas/diverso'))->get('/youtube/google');
        $ida->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertStringContainsString('youtube.readonly', $q['scope']);
        $this->assertSame(route('youtube.google.retorno'), $q['redirect_uri']);

        $idToken = 'x.' . rtrim(strtr(base64_encode(json_encode(['email' => 'Pessoa@Gmail.com'])), '+/', '-_'), '=') . '.y';
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.y', 'refresh_token' => '1//y', 'expires_in' => 3600, 'id_token' => $idToken]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UCabc', 'snippet' => ['title' => 'Meu Canal', 'customUrl' => '@meu'],
                'statistics' => ['subscriberCount' => '1200', 'viewCount' => '50000', 'videoCount' => '2'], 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUabc']]]]]),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [['contentDetails' => ['videoId' => 'vid1']]]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [['id' => 'vid1', 'snippet' => ['title' => 'Vídeo 1', 'publishedAt' => '2026-11-01T10:00:00Z'],
                'statistics' => ['viewCount' => '10'], 'contentDetails' => ['duration' => 'PT1H2M3S'], 'status' => ['privacyStatus' => 'unlisted']]]]),
            'www.googleapis.com/youtube/v3/playlists*' => Http::response(['items' => [['id' => 'PLx', 'snippet' => ['title' => 'Aulas'], 'contentDetails' => ['itemCount' => 3]]]]),
        ]);

        // estado errado não conecta
        $this->actingAs($u)->get('/youtube/google/retorno?state=outro&code=abc')->assertRedirect(url('/areas/diverso'));
        $this->assertSame(0, GoogleConexao::count());

        $this->actingAs($u)->from(url('/areas/diverso'))->get('/youtube/google');
        $this->actingAs($u)->get('/youtube/google/retorno?state=' . session('youtube.estado') . '&code=abc')->assertRedirect(url('/areas/diverso'));
        $con = GoogleConexao::firstWhere('user_id', $u->id);
        $this->assertSame('pessoa@gmail.com', $con->email);
        $this->assertNotSame('1//y', $con->getRawOriginal('refresh_token')); // criptografado

        $this->actingAs($u)->getJson('/youtube/cartao')->assertOk()->assertJson([
            'conectado' => true, 'canal' => ['nome' => 'Meu Canal', 'inscritos' => 1200],
            'videos' => [['id' => 'vid1', 'duracao' => '1:02:03', 'privacidade' => 'unlisted']], 'playlists' => [['id' => 'PLx', 'qtd' => 3]],
        ]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'channels') && str_contains($r->url(), 'mine=true') && $r->header('Authorization')[0] === 'Bearer ya29.y');

        $this->actingAs($u)->deleteJson('/youtube/google')->assertOk();
        $this->assertSame(0, GoogleConexao::count());
    }

    public function test_youtube_duracao(): void
    {
        $this->assertSame('4:05', Youtube::duracao('PT4M5S'));
        $this->assertSame('0:30', Youtube::duracao('PT30S'));
        $this->assertSame('', Youtube::duracao('xx'));
    }

    // ------------------------------------------------------------------ Lembretes

    public function test_lembretes_lista_liga_email_e_testa_envio(): void
    {
        $u = User::factory()->create();
        $amanha = now('America/Sao_Paulo')->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
        $e = Evento::forceCreate(['user_id' => $u->id, 'titulo' => 'Dentista', 'inicio' => $amanha, 'repete' => 'nao', 'lembrete_min' => 30, 'lembrar_email' => false, 'cor' => '#2f6fc7', 'dia_todo' => false]);
        Evento::forceCreate(['user_id' => $u->id, 'titulo' => 'Sem lembrete', 'inicio' => $amanha, 'repete' => 'nao', 'cor' => '#2f6fc7', 'dia_todo' => false]);

        $this->actingAs($u)->getJson('/lembretes/cartao')->assertOk()
            ->assertJsonCount(1, 'lembretes')->assertJsonPath('lembretes.0.evento.titulo', 'Dentista')->assertJsonPath('envio.email', false);

        $this->actingAs($u)->patchJson("/lembretes/{$e->id}", ['lembrar_email' => true])->assertOk();
        $this->assertTrue($e->fresh()->lembrar_email);
        $this->actingAs($u)->postJson('/lembretes/email', ['ligar' => false])->assertOk()->assertJson(['mudados' => 1]);
        $this->assertFalse($e->fresh()->lembrar_email);
        $this->actingAs(User::factory()->create())->patchJson("/lembretes/{$e->id}", ['lembrar_email' => true])->assertNotFound();

        // sem servidor de e-mail de verdade, o teste avisa; com ele, manda para a própria conta
        $this->actingAs($u)->postJson('/lembretes/teste')->assertStatus(422);
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.password' => 'senha-de-verdade']);
        $this->actingAs($u)->postJson('/lembretes/teste')->assertOk();
        Mail::assertSent(LembreteEvento::class, fn ($m) => $m->hasTo($u->email));
    }
}
