<?php

namespace Tests\Feature;

use App\Models\ArmazenamentoDestino;
use App\Models\EmailConta;
use App\Models\EmailMensagem;
use App\Models\GoogleConexao;
use App\Models\User;
use App\Support\Emails\EnviarGmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cartões do quadro mexendo no Google: Drive (pastas, arquivos novos, mover, lixeira, baixar), Gmail (enviar,
 * responder, encaminhar com gmail.send) e YouTube (playlists e edição de vídeo com o escopo youtube).
 * Os escopos novos são opcionais: sem eles, as rotas respondem 409 com o endereço para permitir.
 */
class IntegracoesManipularTest extends TestCase
{
    use RefreshDatabase;

    private const YT_EDITAR = 'https://www.googleapis.com/auth/youtube';
    private const GMAIL_ENVIAR = 'https://www.googleapis.com/auth/gmail.send';

    protected function setUp(): void
    {
        parent::setUp();
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
            'credenciais' => ['access_token' => 'ya29.t', 'refresh_token' => '1//r', 'expira_em' => time() + 3600, 'email' => 'pessoa@gmail.com', 'pasta_id' => 'pasta-wf-raiz'],
        ]);
    }

    private function gmail(User $u, ?string $escopos): EmailConta
    {
        return EmailConta::create(['user_id' => $u->id, 'tipo' => 'gmail', 'endereco' => 'pessoa@gmail.com', 'host' => 'gmail.googleapis.com',
            'access_token' => 'ya29.g', 'refresh_token' => '1//g', 'token_expira_em' => now()->addHour(), 'escopos' => $escopos]);
    }

    private function youtube(User $u, string $escopos): GoogleConexao
    {
        return GoogleConexao::forceCreate(['user_id' => $u->id, 'servico' => 'youtube', 'email' => 'pessoa@gmail.com', 'access_token' => 'ya29.y',
            'refresh_token' => '1//y', 'expira_em' => now()->addHour(), 'dados' => ['escopos' => $escopos]]);
    }

    private static function b64(array $dados): array
    {
        return ['b64' => base64_encode(json_encode($dados))];
    }

    // ------------------------------------------------------------------ Google Drive

    public function test_drive_navega_em_subpasta_e_lista_a_lixeira(): void
    {
        $u = User::factory()->create();
        $this->drive($u);
        Http::fake(['www.googleapis.com/drive/v3/files?*' => Http::response(['files' => [['id' => 'sub-pasta-1', 'name' => 'Clientes', 'mimeType' => 'application/vnd.google-apps.folder']]])]);

        $this->actingAs($u)->getJson('/google/drive?pasta=sub-pasta-1')->assertOk()
            ->assertJson(['raiz' => 'pasta-wf-raiz', 'arquivos' => [['id' => 'sub-pasta-1', 'mime' => 'application/vnd.google-apps.folder']]]);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), "'sub-pasta-1' in parents and trashed = false"));

        $this->actingAs($u)->getJson('/google/drive?lixeira=1')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), 'q=trashed = true') || str_contains(urldecode($r->url()), 'q=trashed+=+true'));

        $this->actingAs($u)->getJson("/google/drive?pasta=x'or'1")->assertStatus(422);
    }

    public function test_drive_cria_pasta_e_documento_do_google(): void
    {
        $u = User::factory()->create();
        $this->drive($u);
        Http::fake(['www.googleapis.com/drive/v3/files?fields=*' => Http::response(['id' => 'doc-novo-1', 'name' => 'Ata', 'mimeType' => 'application/vnd.google-apps.document', 'webViewLink' => 'https://docs.google.com/document/d/doc-novo-1/edit'])]);

        $this->actingAs($u)->postJson('/google/drive/novo', ['tipo' => 'documento', 'nome' => 'Ata/reunião', 'pasta' => 'sub-pasta-1'])->assertOk()
            ->assertJson(['ok' => true, 'arquivo' => ['id' => 'doc-novo-1', 'link' => 'https://docs.google.com/document/d/doc-novo-1/edit']]);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r['mimeType'] === 'application/vnd.google-apps.document'
            && $r['name'] === 'Ata-reunião' && $r['parents'] === ['sub-pasta-1']);

        $this->actingAs($u)->postJson('/google/drive/novo', ['tipo' => 'pasta', 'nome' => ''])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && ($r['mimeType'] ?? '') === 'application/vnd.google-apps.folder'
            && $r['name'] === 'Pasta sem título' && $r['parents'] === ['pasta-wf-raiz']);

        $this->actingAs($u)->postJson('/google/drive/novo', ['tipo' => 'video', 'nome' => 'x'])->assertStatus(422);
        $this->actingAs(User::factory()->create())->postJson('/google/drive/novo', ['tipo' => 'pasta'])->assertStatus(409);
    }

    public function test_drive_move_manda_para_lixeira_restaura_e_apaga(): void
    {
        $u = User::factory()->create();
        $this->drive($u);
        Http::fake(function (HttpRequest $r) {
            $url = urldecode($r->url());
            if ($r->method() === 'GET' && str_contains($url, 'files/arquivo-123') && str_contains($url, 'fields=parents')) return Http::response(['parents' => ['pasta-wf-raiz']]);
            if ($r->method() === 'GET' && str_contains($url, 'files/arquivo-123')) return Http::response(['id' => 'arquivo-123', 'name' => 'nota.md', 'trashed' => true]);
            if ($r->method() === 'GET' && str_contains($url, 'files/arquivo-456')) return Http::response(['id' => 'arquivo-456', 'name' => 'outro.md', 'trashed' => false]);
            if ($r->method() === 'GET' && str_contains($url, 'drive/v3/files?')) return Http::response(['files' => []]);

            return Http::response(['id' => 'arquivo-123']);
        });

        $this->actingAs($u)->patchJson('/google/drive/arquivos/arquivo-123/mover', ['pasta' => 'sub-pasta-1'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && str_contains($r->url(), 'addParents=sub-pasta-1') && str_contains($r->url(), 'removeParents=pasta-wf-raiz'));

        $this->actingAs($u)->patchJson('/google/drive/arquivos/arquivo-123/lixeira', ['lixeira' => true])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && ($r->data()['trashed'] ?? null) === true);
        $this->actingAs($u)->patchJson('/google/drive/arquivos/arquivo-123/lixeira', ['lixeira' => false])->assertOk()->assertJson(['msg' => 'Arquivo restaurado.']);

        // a pasta Work Flower não sai do lugar nem vai para a lixeira
        $this->actingAs($u)->patchJson('/google/drive/arquivos/pasta-wf-raiz/lixeira', ['lixeira' => true])->assertStatus(422);
        $this->actingAs($u)->patchJson('/google/drive/arquivos/pasta-wf-raiz/mover', ['pasta' => 'sub-pasta-1'])->assertStatus(422);

        // apagar de vez: só o que já está na lixeira
        $this->actingAs($u)->deleteJson('/google/drive/arquivos/arquivo-456')->assertStatus(422);
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'arquivo-456'));
        $this->actingAs($u)->deleteJson('/google/drive/arquivos/arquivo-123')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'files/arquivo-123'));
    }

    public function test_drive_lista_pastas_e_baixa_arquivo_e_documento(): void
    {
        $u = User::factory()->create();
        $this->drive($u);
        Http::fake(function (HttpRequest $r) {
            $url = urldecode($r->url());
            if (str_contains($url, 'files/planilha-99/export')) return Http::response('XLSX');
            if (str_contains($url, 'files/planilha-99')) return Http::response(['id' => 'planilha-99', 'name' => 'Contas', 'mimeType' => 'application/vnd.google-apps.spreadsheet']);
            if (str_contains($url, 'files/foto-12345') && str_contains($url, 'alt=media')) return Http::response('PNGDATA');
            if (str_contains($url, 'files/foto-12345')) return Http::response(['id' => 'foto-12345', 'name' => 'foto.png', 'mimeType' => 'image/png', 'size' => '7']);
            if (str_contains($url, 'files/grande-123')) return Http::response(['id' => 'grande-123', 'name' => 'aula.mp4', 'mimeType' => 'video/mp4', 'size' => (string) (90 * 1024 * 1024)]);
            if (str_contains($url, 'drive/v3/files?')) return Http::response(['files' => [['id' => 'pasta-wf-raiz', 'name' => 'Work Flower'], ['id' => 'sub-pasta-1', 'name' => 'Clientes', 'parents' => ['pasta-wf-raiz']]]]);

            return Http::response([], 404);
        });

        $this->actingAs($u)->getJson('/google/drive/pastas')->assertOk()
            ->assertJson(['raiz' => 'pasta-wf-raiz', 'pastas' => [['id' => 'pasta-wf-raiz'], ['id' => 'sub-pasta-1', 'pai' => 'pasta-wf-raiz']]]);

        $r = $this->actingAs($u)->get('/google/drive/arquivos/planilha-99/baixar')->assertOk();
        $this->assertSame('XLSX', $r->getContent());
        $this->assertStringContainsString('Contas.xlsx', $r->headers->get('Content-Disposition'));
        Http::assertSent(fn (HttpRequest $q) => str_contains(urldecode($q->url()), 'mimeType=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));

        $r = $this->actingAs($u)->get('/google/drive/arquivos/foto-12345/baixar')->assertOk();
        $this->assertSame('PNGDATA', $r->getContent());
        $this->assertSame('image/png', $r->headers->get('Content-Type'));

        $this->actingAs($u)->getJson('/google/drive/arquivos/grande-123/baixar')->assertStatus(422);
        Http::assertNotSent(fn (HttpRequest $q) => str_contains($q->url(), 'grande-123') && str_contains($q->url(), 'alt=media'));
    }

    public function test_drive_envia_cartao_para_subpasta(): void
    {
        $u = User::factory()->create();
        $this->drive($u);
        Http::fake(['www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'novo1'])]);

        $this->actingAs($u)->postJson('/google/drive/arquivos', ['nome' => 'nota.md', 'mime' => 'text/markdown', 'b64' => base64_encode('# Oi'), 'pasta' => 'sub-pasta-1'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->body(), '"parents":["sub-pasta-1"]'));
    }

    // ------------------------------------------------------------------ Gmail

    public function test_gmail_sem_permissao_de_envio_pede_para_permitir(): void
    {
        $u = User::factory()->create();
        $conta = $this->gmail($u, 'https://www.googleapis.com/auth/gmail.readonly');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.n', 'expires_in' => 3600, 'scope' => 'https://www.googleapis.com/auth/gmail.readonly ' . self::GMAIL_ENVIAR]),
            'gmail.googleapis.com/*/profile' => Http::response(['emailAddress' => 'pessoa@gmail.com']),
        ]);

        $this->actingAs($u)->getJson('/negocios/emails/cartao')->assertOk()
            ->assertJsonPath('contas.0.pode_enviar', false)
            ->assertJsonPath('permitir_enviar', route('emails.google', ['de' => 'cartao', 'enviar' => 1]));

        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'novo', 'para' => 'ana@site.com', 'assunto' => 'Oi', 'texto' => 'Olá']))
            ->assertStatus(409)->assertJson(['permitir' => route('emails.google', ['de' => 'cartao', 'enviar' => 1, 'conta' => $conta->id])]);
        Http::assertNothingSent();

        // "Permitir enviar" pede o gmail.send junto com o que já foi liberado, na conta certa
        $ida = $this->actingAs($u)->from(url('/areas/diverso'))->get('/negocios/emails/google?de=cartao&enviar=1&conta=' . $conta->id);
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertStringContainsString('gmail.send', $q['scope']);
        $this->assertStringContainsString('gmail.readonly', $q['scope']);
        $this->assertSame('true', $q['include_granted_scopes']);
        $this->assertSame('pessoa@gmail.com', $q['login_hint']);

        // a volta do Google grava os escopos liberados
        $this->actingAs($u)->get('/negocios/emails/google/retorno?state=' . array_key_first(session('emails_state')) . '&code=abc')
            ->assertRedirect(url('/areas/diverso'))->assertSessionHas('ok');
        $this->assertTrue($conta->fresh()->paraTela()['pode_enviar']);
        $this->assertSame('1//g', $conta->fresh()->refresh_token); // sem refresh_token novo, fica o antigo
    }

    public function test_gmail_conexao_normal_nao_pede_envio(): void
    {
        $ida = $this->actingAs(User::factory()->create())->get('/negocios/emails/google?de=cartao');
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertStringNotContainsString('gmail.send', $q['scope']);
    }

    public function test_gmail_envia_responde_e_encaminha(): void
    {
        $u = User::factory()->create();
        $conta = $this->gmail($u, 'https://www.googleapis.com/auth/gmail.readonly ' . self::GMAIL_ENVIAR);
        $m = EmailMensagem::create(['user_id' => $u->id, 'conta_id' => $conta->id, 'uid' => 'g1', 'message_id' => '<orig@mail>', 'de_nome' => 'Cliente',
            'de_email' => 'cliente@loja.com', 'assunto' => 'Orçamento', 'trecho' => 'Oi', 'recebido_em' => now(), 'lido' => false, 'categoria' => 'Clientes']);
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'enviado1', 'threadId' => 't1']),
            'gmail.googleapis.com/gmail/v1/users/me/messages/g1?format=metadata*' => Http::response(['threadId' => 'fio-1', 'payload' => ['headers' => [
                ['name' => 'Message-ID', 'value' => '<orig@mail>'], ['name' => 'References', 'value' => '<antes@mail>'],
            ]]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/g1?format=full' => Http::response(['payload' => ['mimeType' => 'text/plain', 'body' => ['data' => $b64('Texto original')]]]),
        ]);
        $raw = fn (HttpRequest $r) => base64_decode(strtr((string) $r['raw'], '-_', '+/'));

        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'novo', 'para' => 'Ana <ana@site.com>, joao@site.com',
            'cc' => 'chefe@site.com', 'assunto' => 'Proposta é boa', 'texto' => "Olá!\nSegue."]))->assertOk()->assertJson(['ok' => true, 'id' => 'enviado1']);
        Http::assertSent(function (HttpRequest $r) use ($raw) {
            if (! str_ends_with($r->url(), '/messages/send')) return false;
            $mime = $raw($r);

            return str_contains($mime, "To: Ana <ana@site.com>, joao@site.com\r\n") && str_contains($mime, "Cc: chefe@site.com\r\n")
                && str_contains($mime, 'Subject: =?UTF-8?B?' . base64_encode('Proposta é boa') . '?=') && str_contains($mime, 'From: pessoa@gmail.com')
                && str_contains($mime, base64_encode("Olá!\r\nSegue.")) && ! isset($r->data()['threadId']);
        });

        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'responder', 'mensagem' => $m->id,
            'para' => 'cliente@loja.com', 'assunto' => 'Re: Orçamento', 'texto' => 'Combinado.']))->assertOk()->assertJson(['msg' => 'E-mail respondido pelo pessoa@gmail.com.']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages/send') && ($r->data()['threadId'] ?? null) === 'fio-1'
            && str_contains($raw($r), "In-Reply-To: <orig@mail>\r\n") && str_contains($raw($r), "References: <antes@mail> <orig@mail>\r\n"));

        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'encaminhar', 'mensagem' => $m->id,
            'para' => 'socio@site.com', 'assunto' => 'Fwd: Orçamento', 'texto' => 'Veja abaixo.']))->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages/send')
            && str_contains(base64_decode(implode('', array_slice(explode("\r\n\r\n", $raw($r), 2), 1))), 'Texto original'));

        // cabeçalho com quebra de linha (tentativa de pôr destinatário escondido) e e-mail inválido
        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'novo', 'para' => "ana@site.com\r\nBcc: x@y.com", 'texto' => 'x']))->assertStatus(422);
        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'novo', 'para' => 'não é e-mail', 'texto' => 'x']))->assertStatus(422);
        // caixa e mensagem de outra pessoa
        $outro = User::factory()->create();
        $this->actingAs($outro)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'novo', 'para' => 'a@b.com']))->assertNotFound();
        $c2 = $this->gmail($outro, self::GMAIL_ENVIAR);
        $this->actingAs($outro)->postJson('/negocios/emails/enviar', self::b64(['conta' => $c2->id, 'modo' => 'responder', 'mensagem' => $m->id, 'para' => 'a@b.com']))->assertNotFound();
    }

    public function test_gmail_caixa_imap_nao_envia(): void
    {
        $u = User::factory()->create();
        $c = EmailConta::create(['user_id' => $u->id, 'tipo' => 'imap', 'endereco' => 'eu@titan.com', 'host' => 'imap.titan.email', 'porta' => 993, 'seguranca' => 'ssl', 'senha' => 'x']);
        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $c->id, 'modo' => 'novo', 'para' => 'a@b.com']))->assertStatus(422);
    }

    public function test_gmail_permissao_tirada_no_google_volta_a_pedir(): void
    {
        $u = User::factory()->create();
        $conta = $this->gmail($u, 'https://www.googleapis.com/auth/gmail.readonly ' . self::GMAIL_ENVIAR);
        Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.']], 403)]);

        $this->actingAs($u)->postJson('/negocios/emails/enviar', self::b64(['conta' => $conta->id, 'modo' => 'novo', 'para' => 'a@b.com', 'texto' => 'x']))
            ->assertStatus(409)->assertJsonStructure(['erro', 'permitir']);
        $this->assertFalse($conta->fresh()->paraTela()['pode_enviar']);
    }

    public function test_mime_do_gmail(): void
    {
        $mime = EnviarGmail::mime('eu@gmail.com', ['a@b.com'], [], "Assunto\r\nBcc: x@y.com", "linha 1\nlinha 2");
        $this->assertStringContainsString("Subject: Assunto Bcc: x@y.com\r\n", $mime);
        $this->assertStringNotContainsString("\r\nBcc:", $mime);
    }

    // ------------------------------------------------------------------ YouTube

    public function test_youtube_sem_escopo_de_edicao_mostra_permitir_e_recusa_mudar(): void
    {
        $u = User::factory()->create();
        $this->youtube($u, 'https://www.googleapis.com/auth/youtube.readonly openid email');
        Http::fake(['www.googleapis.com/youtube/v3/playlists*' => Http::response(['items' => [['id' => 'PLlista12345', 'snippet' => ['title' => 'Aulas'], 'contentDetails' => ['itemCount' => 2], 'status' => ['privacyStatus' => 'private']]]])]);

        // listar continua funcionando só com o readonly
        $this->actingAs($u)->getJson('/youtube/playlists')->assertOk()->assertJson(['playlists' => [['id' => 'PLlista12345', 'privacidade' => 'private']]]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'mine=true'));

        $this->actingAs($u)->postJson('/youtube/playlists', ['titulo' => 'Nova', 'privacidade' => 'private'])
            ->assertStatus(409)->assertJson(['permitir' => route('youtube.google', ['editar' => 1])]);
        $this->actingAs($u)->patchJson('/youtube/videos/abcdefghijk', ['titulo' => 'x'])->assertStatus(409);
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() !== 'GET');

        // "Permitir editar" pede o escopo youtube junto com o que já foi liberado
        $ida = $this->actingAs($u)->from(url('/areas/diverso'))->get('/youtube/google?editar=1');
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertContains(self::YT_EDITAR, explode(' ', $q['scope']));
        $this->assertSame('true', $q['include_granted_scopes']);
        $this->assertSame('pessoa@gmail.com', $q['login_hint']);

        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.n', 'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/youtube.readonly ' . self::YT_EDITAR . ' openid'])]);
        $this->actingAs($u)->get('/youtube/google/retorno?state=' . session('youtube.estado') . '&code=abc')->assertRedirect(url('/areas/diverso'))->assertSessionHas('ok');
        $this->assertSame('1//y', GoogleConexao::first()->refresh_token);

        Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []])]);
        $this->actingAs($u)->getJson('/youtube/cartao')->assertJson(['pode_editar' => true]);

        // conexão normal não pede edição
        $ida = $this->actingAs(User::factory()->create())->get('/youtube/google');
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertNotContains(self::YT_EDITAR, explode(' ', $q['scope']));
    }

    public function test_youtube_permissao_desmarcada_no_google_avisa(): void
    {
        $u = User::factory()->create();
        $this->youtube($u, 'https://www.googleapis.com/auth/youtube.readonly');
        $this->actingAs($u)->from(url('/areas/diverso'))->get('/youtube/google?editar=1');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.n', 'expires_in' => 3600, 'scope' => 'https://www.googleapis.com/auth/youtube.readonly'])]);
        $this->actingAs($u)->get('/youtube/google/retorno?state=' . session('youtube.estado') . '&code=abc')->assertSessionHas('erro');
    }

    public function test_youtube_cria_playlist_poe_e_tira_video(): void
    {
        $u = User::factory()->create();
        $this->youtube($u, 'https://www.googleapis.com/auth/youtube.readonly ' . self::YT_EDITAR);
        Http::fake(function (HttpRequest $r) {
            if ($r->method() === 'POST' && str_contains($r->url(), 'youtube/v3/playlists?')) return Http::response(['id' => 'PLnova123456', 'snippet' => ['title' => 'Cortes']]);
            if ($r->method() === 'POST' && str_contains($r->url(), 'playlistItems')) return Http::response(['id' => 'item1']);
            if ($r->method() === 'GET' && str_contains($r->url(), 'playlistItems')) return Http::response(['items' => [['id' => 'itemA'], ['id' => 'itemB']]]);
            if ($r->method() === 'DELETE') return Http::response('', 204);

            return Http::response([], 404);
        });

        $this->actingAs($u)->postJson('/youtube/playlists', self::b64(['titulo' => 'Cortes', 'descricao' => 'Melhores', 'privacidade' => 'unlisted']))
            ->assertOk()->assertJson(['playlist' => ['id' => 'PLnova123456', 'titulo' => 'Cortes']]);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), 'part=snippet%2Cstatus')
            && $r['status']['privacyStatus'] === 'unlisted' && $r['snippet']['title'] === 'Cortes' && $r->header('Authorization')[0] === 'Bearer ya29.y');
        $this->actingAs($u)->postJson('/youtube/playlists', ['titulo' => 'x', 'privacidade' => 'secreta'])->assertStatus(422);

        $this->actingAs($u)->postJson('/youtube/playlists/PLnova123456/videos', ['video' => 'abcdefghijk'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), 'playlistItems')
            && $r['snippet']['playlistId'] === 'PLnova123456' && $r['snippet']['resourceId']['videoId'] === 'abcdefghijk');
        $this->actingAs($u)->postJson('/youtube/playlists/PLnova123456/videos', ['video' => 'curto'])->assertStatus(422);

        $this->actingAs($u)->deleteJson('/youtube/playlists/PLnova123456/videos/abcdefghijk')->assertOk()->assertJson(['removidos' => 2]);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'id=itemA'));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'id=itemB'));

        // sem conexão
        $this->actingAs(User::factory()->create())->postJson('/youtube/playlists', ['titulo' => 'x', 'privacidade' => 'public'])->assertStatus(409);
    }

    public function test_youtube_edita_video_mantendo_os_outros_campos(): void
    {
        $u = User::factory()->create();
        $this->youtube($u, self::YT_EDITAR);
        Http::fake(function (HttpRequest $r) {
            if ($r->method() === 'GET') return Http::response(['items' => [['id' => 'abcdefghijk',
                'snippet' => ['title' => 'Antigo', 'description' => 'Desc antiga', 'categoryId' => '27', 'tags' => ['aula'], 'channelTitle' => 'X'],
                'status' => ['privacyStatus' => 'private', 'publishAt' => '2026-12-01T10:00:00Z', 'uploadStatus' => 'processed', 'selfDeclaredMadeForKids' => false, 'embeddable' => true]]]]);
            if ($r->method() === 'PUT') return Http::response(['id' => 'abcdefghijk', 'snippet' => ['title' => $r['snippet']['title'], 'description' => $r['snippet']['description']], 'status' => $r['status']]);

            return Http::response([], 404);
        });

        $this->actingAs($u)->patchJson('/youtube/videos/abcdefghijk', self::b64(['titulo' => 'Novo título', 'privacidade' => 'public']))->assertOk()
            ->assertJson(['video' => ['titulo' => 'Novo título', 'descricao' => 'Desc antiga', 'privacidade' => 'public']]);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && $r['snippet']['categoryId'] === '27' && $r['snippet']['tags'] === ['aula']
            && ! isset($r['snippet']['channelTitle']) && ! isset($r['status']['uploadStatus']) && ! isset($r['status']['publishAt'])
            && $r['status']['selfDeclaredMadeForKids'] === false && $r['status']['privacyStatus'] === 'public');

        $this->actingAs($u)->patchJson('/youtube/videos/abcdefghijk', ['titulo' => 'Com <tag>'])->assertStatus(422);
        $this->actingAs($u)->patchJson('/youtube/videos/abcdefghijk', ['privacidade' => 'secreta'])->assertStatus(422);
    }

    public function test_youtube_escopo_tirado_no_google_volta_a_pedir(): void
    {
        $u = User::factory()->create();
        $con = $this->youtube($u, 'https://www.googleapis.com/auth/youtube.readonly ' . self::YT_EDITAR);
        Http::fake(['www.googleapis.com/youtube/v3/*' => Http::response(['error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.',
            'errors' => [['reason' => 'insufficientPermissions']]]], 403)]);

        $this->actingAs($u)->postJson('/youtube/playlists', ['titulo' => 'x', 'privacidade' => 'public'])->assertStatus(409)->assertJsonStructure(['erro', 'permitir']);
        $this->assertFalse(\App\Support\Google\Youtube::podeEditar($con->fresh()));
        $this->assertStringContainsString('youtube.readonly', $con->fresh()->dados['escopos']);
    }
}
