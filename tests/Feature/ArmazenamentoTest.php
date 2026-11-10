<?php

namespace Tests\Feature;

use App\Models\ArmazenamentoArquivo;
use App\Models\ArmazenamentoBackup;
use App\Models\ArmazenamentoDestino;
use App\Models\CorpoDado;
use App\Models\User;
use App\Support\Armazenamento\S3;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Configurações › Armazenamento: uso, destinos S3/Drive, backups, restauração e arquivos fora do banco. */
class ArmazenamentoTest extends TestCase
{
    use RefreshDatabase;

    private const R2 = 'https://conta123.r2.cloudflarestorage.com';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // PDFs da Mente e fotos da loja: nunca o disco de verdade
    }

    private function destinoS3(User $u, array $extra = []): ArmazenamentoDestino
    {
        return ArmazenamentoDestino::create(array_merge([
            'user_id' => $u->id, 'tipo' => 's3', 'nome' => 'Cloudflare R2',
            'credenciais' => ['endpoint' => self::R2, 'regiao' => '', 'bucket' => 'meus-backups', 'chave' => 'AKIDTESTE', 'segredo' => 'segredo-teste', 'prefixo' => 'work-flower'],
            'resumo' => 'meus-backups/work-flower',
        ], $extra));
    }

    private function png(int $bytes): string
    {
        // como o quadro grava: JSON.stringify(dataUrl), sem escapar a barra
        return json_encode('data:image/png;base64,' . base64_encode("\x89PNG" . str_repeat('x', $bytes)), JSON_UNESCAPED_SLASHES);
    }

    // ------------------------------------------------------------------ S3

    public function test_assinatura_v4_bate_com_o_exemplo_da_aws(): void
    {
        // exemplo oficial de URL pré-assinada da AWS (GET /test.txt, 24 h)
        Carbon::setTestNow(Carbon::parse('2013-05-24 00:00:00', 'UTC'));
        $s3 = new S3(['endpoint' => 'https://examplebucket.s3.amazonaws.com', 'bucket' => '', 'regiao' => 'us-east-1',
            'chave' => 'AKIAIOSFODNN7EXAMPLE', 'segredo' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'prefixo' => '']);

        $url = $s3->urlTemporaria('test.txt', 86400);

        $this->assertStringStartsWith('https://examplebucket.s3.amazonaws.com/test.txt?', $url);
        $this->assertStringEndsWith('X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404', $url);
        Carbon::setTestNow();
    }

    public function test_regiao_sai_do_endpoint(): void
    {
        $this->assertSame('auto', S3::regiaoDoEndpoint(self::R2));
        $this->assertSame('us-west-004', S3::regiaoDoEndpoint('https://s3.us-west-004.backblazeb2.com'));
        $this->assertSame('sa-east-1', S3::regiaoDoEndpoint('https://s3.sa-east-1.amazonaws.com'));
    }

    public function test_conecta_bucket_testando_e_guarda_credenciais_criptografadas(): void
    {
        Http::fake([self::R2 . '/*' => Http::response('', 200)]);
        $u = User::factory()->create();

        $this->actingAs($u)->post('/armazenamento/destinos/s3', [
            'endpoint' => self::R2, 'bucket' => 'meus-backups', 'chave' => 'AKIDTESTE', 'segredo' => 'segredo-muito-secreto',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $d = ArmazenamentoDestino::firstWhere('user_id', $u->id);
        $this->assertSame('Cloudflare R2', $d->nome);
        $this->assertSame('segredo-muito-secreto', $d->credenciais['segredo']);
        $this->assertStringNotContainsString('segredo-muito-secreto', DB::table('armazenamento_destinos')->value('credenciais'));

        // gravou e apagou o arquivo de teste, assinado com a região "auto" do R2
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT'
            && $r->url() === self::R2 . '/meus-backups/work-flower/.teste-conexao.txt'
            && str_contains($r->header('Authorization')[0], '/auto/s3/aws4_request'));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE');
    }

    public function test_bucket_recusado_mostra_o_motivo_e_nao_salva(): void
    {
        Http::fake([self::R2 . '/*' => Http::response('<Error><Code>InvalidAccessKeyId</Code></Error>', 403)]);
        $u = User::factory()->create();

        $this->actingAs($u)->post('/armazenamento/destinos/s3', [
            'endpoint' => self::R2, 'bucket' => 'meus-backups', 'chave' => 'errada', 'segredo' => 'x',
        ])->assertSessionHasErrors('s3');

        $this->assertSame(0, ArmazenamentoDestino::count());
    }

    public function test_recusa_endpoint_da_rede_interna(): void
    {
        Http::fake();
        $u = User::factory()->create();

        $this->actingAs($u)->post('/armazenamento/destinos/s3', [
            'endpoint' => 'https://127.0.0.1:9000', 'bucket' => 'b-1', 'chave' => 'a', 'segredo' => 'b',
        ])->assertSessionHasErrors('endpoint');
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- backups

    public function test_backup_na_hora_envia_o_arquivo_de_exportacao(): void
    {
        Http::fake([self::R2 . '/*' => Http::response('', 200)]);
        $u = User::factory()->create();
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => '{"abas":[]}']);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'mente.estado', 'valor' => '{"livros":[]}']);
        $d = $this->destinoS3($u);

        $this->actingAs($u)->post("/armazenamento/destinos/{$d->id}/backup")->assertSessionHasNoErrors();

        $b = ArmazenamentoBackup::firstWhere('user_id', $u->id);
        $this->assertSame('manual', $b->origem);
        $this->assertStringStartsWith('work-flower/backups/backup-', $b->remoto);
        $this->assertNotNull($d->fresh()->ultimo_backup_em);

        Http::assertSent(function (HttpRequest $r) {
            $j = json_decode($r->body(), true);

            return $r->method() === 'PUT' && ($j['formato'] ?? null) === 'work-flower.workflows'
                && collect($j['workflows'])->firstWhere('area', 'corpo')['dados'][0]['chave'] === 'estado'
                && collect($j['workflows'])->firstWhere('area', 'mente')['dados'][0]['chave'] === 'estado';
        });
    }

    public function test_restaura_area_escolhida_e_guarda_copia_de_antes(): void
    {
        $u = User::factory()->create();
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => 'novo']);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'mente.estado', 'valor' => 'mente-atual']);
        $d = $this->destinoS3($u);
        $b = ArmazenamentoBackup::create(['user_id' => $u->id, 'destino_id' => $d->id, 'remoto' => 'work-flower/backups/b.json', 'nome' => 'b.json', 'areas' => 'corpo,mente']);

        $arquivo = json_encode(['formato' => 'work-flower.workflows', 'versao' => 1, 'workflows' => [
            ['area' => 'corpo', 'dados' => [['chave' => 'estado', 'valor' => 'velho']]],
            ['area' => 'mente', 'dados' => [['chave' => 'estado', 'valor' => 'mente-velha']]],
        ]]);
        Http::fake(fn (HttpRequest $r) => $r->method() === 'GET' ? Http::response($arquivo) : Http::response(''));

        $this->actingAs($u)->post("/armazenamento/backups/{$b->id}/restaurar", ['areas' => ['corpo']])->assertSessionHasNoErrors();

        $this->assertSame('velho', CorpoDado::where(['user_id' => $u->id, 'chave' => 'estado'])->value('valor'));
        $this->assertSame('mente-atual', CorpoDado::where(['user_id' => $u->id, 'chave' => 'mente.estado'])->value('valor'));
        $this->assertTrue(ArmazenamentoBackup::where(['user_id' => $u->id, 'origem' => 'antes'])->exists());
    }

    public function test_nao_mexe_em_destino_ou_backup_de_outra_conta(): void
    {
        Http::fake();
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $d = $this->destinoS3($dono);
        $b = ArmazenamentoBackup::create(['user_id' => $dono->id, 'destino_id' => $d->id, 'remoto' => 'x', 'nome' => 'x.json']);

        $this->actingAs($outro)->post("/armazenamento/destinos/{$d->id}/backup")->assertNotFound();
        $this->actingAs($outro)->delete("/armazenamento/destinos/{$d->id}")->assertNotFound();
        $this->actingAs($outro)->post("/armazenamento/backups/{$b->id}/restaurar", ['areas' => ['corpo']])->assertNotFound();
        $this->actingAs($outro)->get("/armazenamento/backups/{$b->id}/baixar")->assertNotFound();
        $this->actingAs($outro)->post('/armazenamento/arquivos/mover', ['destino_id' => $d->id])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_backup_diario_pelo_agendador_e_apaga_os_antigos(): void
    {
        config(['armazenamento.manter_diarios' => 2]);
        Http::fake([self::R2 . '/*' => Http::response('', 200)]);
        $u = User::factory()->create();
        $ligado = $this->destinoS3($u, ['backup_diario' => true]);
        $desligado = $this->destinoS3(User::factory()->create());
        foreach ([3, 2] as $dias) {
            ArmazenamentoBackup::create(['user_id' => $u->id, 'destino_id' => $ligado->id, 'remoto' => "velho-{$dias}", 'nome' => 'v.json', 'origem' => 'diario'])
                ->forceFill(['created_at' => now()->subDays($dias)])->save();
        }

        $this->artisan('armazenamento:backup-diario')->assertSuccessful();

        $this->assertSame(2, ArmazenamentoBackup::where('destino_id', $ligado->id)->count());
        $this->assertFalse(ArmazenamentoBackup::where('remoto', 'velho-3')->exists());
        $this->assertSame(0, ArmazenamentoBackup::where('destino_id', $desligado->id)->count());
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/velho-3'));

        // rodar de novo no mesmo dia não manda outro
        $this->artisan('armazenamento:backup-diario')->assertSuccessful();
        $this->assertSame(2, ArmazenamentoBackup::where('destino_id', $ligado->id)->count());
    }

    // ------------------------------------------------------------- arquivos

    public function test_leva_imagens_grandes_para_o_bucket_e_traz_de_volta(): void
    {
        config(['armazenamento.imagem_grande' => 1000]);
        $u = User::factory()->create();
        $grande = $this->png(5000);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'img.b1', 'valor' => $grande]);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'img.b2', 'valor' => $this->png(10)]);
        DB::table('diverso_dados')->insert(['user_id' => $u->id, 'chave' => 'img.d1', 'valor' => $this->png(3000), 'created_at' => now(), 'updated_at' => now()]);
        $d = $this->destinoS3($u);
        $binario = base64_decode(substr(json_decode($grande), strlen('data:image/png;base64,')));

        Http::fake(fn (HttpRequest $r) => $r->method() === 'GET' ? Http::response($binario) : Http::response(''));
        $this->actingAs($u)->post('/armazenamento/arquivos/mover', ['destino_id' => $d->id])->assertSessionHasNoErrors();

        $ref = json_decode(CorpoDado::where(['user_id' => $u->id, 'chave' => 'img.b1'])->value('valor'));
        $this->assertMatchesRegularExpression('#^/armazenamento/arquivo/[A-Za-z0-9]{32}$#', $ref);
        $this->assertSame($this->png(10), CorpoDado::where(['user_id' => $u->id, 'chave' => 'img.b2'])->value('valor'), 'imagem pequena fica');
        $this->assertSame(2, ArmazenamentoArquivo::where('user_id', $u->id)->count());

        // o quadro pede a imagem: vai para um link temporário assinado do bucket
        $this->actingAs($u)->get($ref)->assertRedirect()->assertRedirectContains(self::R2 . '/meus-backups/work-flower/arquivos/');
        // quem não tem acesso ao workflow não vê
        $this->actingAs(User::factory()->create())->get($ref)->assertNotFound();
        // com arquivos lá, não dá para desconectar
        $this->actingAs($u)->delete("/armazenamento/destinos/{$d->id}")->assertSessionHasErrors('destino');

        $this->actingAs($u)->post('/armazenamento/arquivos/trazer')->assertSessionHasNoErrors();
        $this->assertSame($grande, CorpoDado::where(['user_id' => $u->id, 'chave' => 'img.b1'])->value('valor'));
        $this->assertSame(0, ArmazenamentoArquivo::count());
    }

    public function test_imagem_trocada_depois_de_levada_vira_orfa_e_sai_do_bucket(): void
    {
        config(['armazenamento.imagem_grande' => 1000]);
        Http::fake([self::R2 . '/*' => Http::response('', 200)]);
        $u = User::factory()->create();
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'img.b1', 'valor' => $this->png(5000)]);
        $d = $this->destinoS3($u);
        $this->actingAs($u)->post('/armazenamento/arquivos/mover', ['destino_id' => $d->id]);
        $this->assertSame(1, ArmazenamentoArquivo::count());

        CorpoDado::where('chave', 'img.b1')->update(['valor' => $this->png(20)]); // o usuário trocou a imagem no quadro
        $this->artisan('armazenamento:backup-diario')->assertSuccessful();

        $this->assertSame(0, ArmazenamentoArquivo::count());
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/arquivos/'));
    }

    public function test_pdf_da_mente_sai_do_disco_e_continua_abrindo(): void
    {
        Storage::fake('local');
        Http::fake([self::R2 . '/*' => Http::response('', 200)]);
        $u = User::factory()->create();
        Storage::disk('local')->put("mente/pdf/{$u->id}/livro1.pdf", '%PDF-1.4 conteudo');
        $d = $this->destinoS3($u);

        $this->actingAs($u)->post('/armazenamento/arquivos/mover', ['destino_id' => $d->id])->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing("mente/pdf/{$u->id}/livro1.pdf");
        $this->actingAs($u)->get('/mente/pdf/livro1')->assertRedirectContains('/meus-backups/work-flower/arquivos/');

        // remover o PDF do livro apaga também a cópia no bucket
        $this->actingAs($u)->delete('/mente/pdf/livro1')->assertOk();
        $this->assertSame(0, ArmazenamentoArquivo::count());
        $this->actingAs($u)->get('/mente/pdf/livro1')->assertNotFound();
    }

    // --------------------------------------------------------- Google Drive

    public function test_conecta_google_drive_e_faz_backup_la(): void
    {
        config(['armazenamento.google.client_id' => 'cliente.apps.googleusercontent.com', 'armazenamento.google.client_secret' => 'segredo']);
        $idToken = 'x.' . rtrim(strtr(base64_encode(json_encode(['email' => 'pessoa@gmail.com'])), '+/', '-_'), '=') . '.y';
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.t', 'refresh_token' => '1//r', 'expires_in' => 3600, 'id_token' => $idToken]),
            'www.googleapis.com/drive/v3/files*' => Http::response(['id' => 'pasta-wf']),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'arquivo-backup-1']),
        ]);
        $u = User::factory()->create();

        config(['services.google.publico' => true]);
        $ida = $this->actingAs($u)->get('/armazenamento/google/conectar');
        $ida->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str(parse_url($ida->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertStringContainsString('drive.file', $q['scope']);

        // estado errado não conecta
        $this->actingAs($u)->get('/armazenamento/google/retorno?state=outro&code=abc')->assertSessionHasErrors('destino');

        $this->actingAs($u)->get('/armazenamento/google/conectar');
        $estado = session('armazenamento.google_estado');
        $this->actingAs($u)->get('/armazenamento/google/retorno?state=' . $estado . '&code=abc')->assertSessionHasNoErrors();

        $d = ArmazenamentoDestino::firstWhere(['user_id' => $u->id, 'tipo' => 'gdrive']);
        $this->assertStringContainsString('pessoa@gmail.com', $d->resumo);
        $this->assertSame('pasta-wf', $d->credenciais['pasta_id']);

        $this->actingAs($u)->post("/armazenamento/destinos/{$d->id}/backup")->assertSessionHasNoErrors();
        $this->assertSame('arquivo-backup-1', ArmazenamentoBackup::firstWhere('destino_id', $d->id)->remoto);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=multipart')
            && $r->header('Authorization')[0] === 'Bearer ya29.t'
            && str_contains($r->body(), '"parents":["pasta-wf"]'));
    }

    public function test_google_drive_desligado_sem_app_configurado(): void
    {
        config(['armazenamento.google.client_id' => '', 'armazenamento.google.client_secret' => '']);
        $u = User::factory()->create();

        $this->actingAs($u)->get('/armazenamento/google/conectar')->assertRedirect('/armazenamento')->assertSessionHasErrors('destino');
        $this->actingAs($u)->get('/armazenamento')->assertOk()->assertSee('Google Drive (indisponível)');
    }

    // ------------------------------------------------------------------ uso

    public function test_pagina_mostra_o_uso_por_area(): void
    {
        $u = User::factory()->create();
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => str_repeat('a', 2048)]);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'img.x', 'valor' => $this->png(3000)]);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'mente.estado', 'valor' => '{}']);
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'fundo', 'valor' => 'azul']);
        DB::table('negocio_dados')->insert(['user_id' => $u->id, 'chave' => 'estado', 'valor' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        $r = $this->actingAs($u)->get('/armazenamento')->assertOk()->assertSee('Quanto você usa')->assertSee('sem limite de espaço no site');
        $uso = $r->viewData('uso');

        $this->assertSame(2048 + 6, $uso['areas']['corpo']['dados']);
        $this->assertGreaterThan(4000, $uso['areas']['corpo']['imagens']);
        $this->assertSame(2 + 12, $uso['areas']['mente']['dados']);
        $this->assertSame(1, $uso['areas']['conta']['registros']);
        $this->assertSame(1, $uso['areas']['negocios']['registros']);
        $this->assertSame(0, $r->viewData('pendentes'), 'imagem de 3 KB fica abaixo do limite padrão de 100 KB');
    }

    // ------------------------------------------------------ envio em partes

    public function test_arquivo_grande_vai_ao_bucket_em_partes_de_8_mb(): void
    {
        Http::fake(function (HttpRequest $r) {
            if ($r->method() === 'POST' && str_contains($r->url(), '?uploads=')) {
                return Http::response('<InitiateMultipartUploadResult><UploadId>up-123</UploadId></InitiateMultipartUploadResult>');
            }
            if ($r->method() === 'PUT') {
                parse_str(parse_url($r->url(), PHP_URL_QUERY), $q);

                return Http::response('', 200, ['ETag' => '"etag-' . $q['partNumber'] . '"']);
            }

            return Http::response('<CompleteMultipartUploadResult></CompleteMultipartUploadResult>');
        });
        $s3 = new S3(['endpoint' => self::R2, 'bucket' => 'meus-backups', 'chave' => 'AK', 'segredo' => 'S', 'prefixo' => 'wf']);

        $chave = $s3->enviar('backups/grande.json', str_repeat('a', 17 * 1024 * 1024), 'application/json');

        $this->assertSame('wf/backups/grande.json', $chave);
        $partes = Http::recorded(fn (HttpRequest $r) => $r->method() === 'PUT')->map(fn ($p) => strlen($p[0]->body()))->values()->all();
        $this->assertSame([8 * 1024 * 1024, 8 * 1024 * 1024, 1024 * 1024], $partes);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), 'uploadId=up-123')
            && str_contains($r->body(), '<PartNumber>3</PartNumber><ETag>"etag-3"</ETag>'));
    }

    public function test_parte_recusada_cancela_o_envio_no_bucket(): void
    {
        Http::fake(function (HttpRequest $r) {
            if ($r->method() === 'POST') return Http::response('<UploadId>up-9</UploadId>');
            if ($r->method() === 'PUT') return Http::response('<Error><Code>AccessDenied</Code></Error>', 403);

            return Http::response('', 204);
        });
        $s3 = new S3(['endpoint' => self::R2, 'bucket' => 'b-1', 'chave' => 'AK', 'segredo' => 'S']);

        try {
            $s3->enviar('x.json', str_repeat('a', 9 * 1024 * 1024), 'application/json');
            $this->fail('devia falhar');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('parte 1 de 2', $e->getMessage());
        }
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'uploadId=up-9'));
    }

    public function test_arquivo_grande_vai_ao_drive_em_partes(): void
    {
        Http::fake([
            'www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/sessao-1']),
            'www.googleapis.com/upload/sessao-1' => Http::sequence()->push('', 308)->push(['id' => 'arquivo-grande']),
        ]);
        $u = User::factory()->create();
        $d = ArmazenamentoDestino::create(['user_id' => $u->id, 'tipo' => 'gdrive', 'nome' => 'Google Drive',
            'credenciais' => ['access_token' => 'ya29.t', 'refresh_token' => 'r', 'expira_em' => time() + 3600, 'pasta_id' => 'pasta-wf']]);

        $id = $d->cliente()->enviar('backups/grande.json', str_repeat('a', 9 * 1024 * 1024), 'application/json');

        $this->assertSame('arquivo-grande', $id);
        Http::assertSent(fn (HttpRequest $r) => ($r->header('Content-Range')[0] ?? '') === 'bytes 0-8388607/9437184');
        Http::assertSent(fn (HttpRequest $r) => ($r->header('Content-Range')[0] ?? '') === 'bytes 8388608-9437183/9437184');
    }

    public function test_administracao_ve_como_liberar_o_google_drive(): void
    {
        config(['armazenamento.google.client_id' => '', 'armazenamento.google.client_secret' => '']);
        $admin = User::factory()->create(['email' => User::ADMINS[0]]);

        $this->actingAs($admin)->get('/armazenamento')->assertOk()
            ->assertSee('Para liberar o Google Drive')
            ->assertSee(route('armazenamento.google.retorno'))
            ->assertSee('GOOGLE_CLIENT_ID=');
        $this->actingAs(User::factory()->create())->get('/armazenamento')->assertDontSee('Para liberar o Google Drive');
    }

    public function test_nome_comprido_tem_dica_e_nome_com_aspas_nao_quebra_o_aviso(): void
    {
        $u = User::factory()->create();
        $this->destinoS3($u, ['nome' => "D'Ávila </script> backups"]);

        $this->actingAs($u)->get('/armazenamento')->assertOk()
            ->assertSee('title="D&#039;Ávila &lt;/script&gt; backups"', false)
            ->assertDontSee("</script> backups", false);
    }

    public function test_precisa_estar_logado(): void
    {
        $this->get('/armazenamento')->assertRedirect('/login');
    }
}
