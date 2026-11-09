<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verificação em duas etapas: app autenticador (TOTP) + códigos de recuperação. */
class DoisFatoresTest extends TestCase
{
    use RefreshDatabase;

    private const SEGREDO = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private function contaCom2fa(array $codigosRecuperacao = []): User
    {
        $u = User::factory()->create(['email' => 'ana@exemplo.com']);
        $u->forceFill([
            'dois_fatores_segredo'     => self::SEGREDO,
            'dois_fatores_recuperacao' => array_map(fn ($c) => hash_hmac('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c)), (string) config('app.key')), $codigosRecuperacao),
            'dois_fatores_ativo_em'    => now(),
        ])->save();

        return $u->fresh();
    }

    private function codigoAgora(): string
    {
        return Totp::codigo(self::SEGREDO, Totp::passoAtual());
    }

    public function test_totp_bate_com_o_vetor_da_rfc_6238(): void
    {
        // RFC 6238, SHA1, segredo "12345678901234567890", T = 59 s → 94287082 (8 dígitos) → 287082 (6)
        $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::codigo($segredo, intdiv(59, 30)));
        $this->assertSame(1, Totp::verificar($segredo, '287 082', null, 59));
        $this->assertNull(Totp::verificar($segredo, '287082', 1, 59), 'o mesmo passo não serve duas vezes');
        $this->assertNull(Totp::verificar($segredo, '000000', null, 59));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', Totp::gerarSegredo());
    }

    public function test_conta_sem_2fa_entra_direto_com_a_senha(): void
    {
        User::factory()->create(['email' => 'bia@exemplo.com']);

        $this->post('/login', ['email' => 'bia@exemplo.com', 'password' => 'password'])->assertRedirect(route('painel'));
        $this->assertAuthenticated();
    }

    public function test_conta_com_2fa_pede_o_codigo_depois_da_senha(): void
    {
        $u = $this->contaCom2fa();

        $this->post('/login', ['email' => $u->email, 'password' => 'password', 'lembrar' => '1'])
            ->assertRedirect(route('login.dois-fatores'));
        $this->assertGuest();
        $this->get('/login/dois-fatores')->assertOk()->assertSee('Verificação em duas etapas');

        $this->post('/login/dois-fatores', ['codigo' => '000000'])->assertSessionHasErrors('codigo');
        $this->assertGuest();

        $this->post('/login/dois-fatores', ['codigo' => $this->codigoAgora()])->assertRedirect(route('painel'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_senha_errada_nao_chega_na_tela_do_codigo(): void
    {
        $u = $this->contaCom2fa();

        $this->post('/login', ['email' => $u->email, 'password' => 'errada123'])->assertSessionHasErrors('email');
        $this->get('/login/dois-fatores')->assertRedirect(route('login'));
        $this->post('/login/dois-fatores', ['codigo' => $this->codigoAgora()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_o_mesmo_codigo_nao_entra_duas_vezes(): void
    {
        $u = $this->contaCom2fa();
        $codigo = $this->codigoAgora();

        $this->post('/login', ['email' => $u->email, 'password' => 'password']);
        $this->post('/login/dois-fatores', ['codigo' => $codigo])->assertRedirect(route('painel'));
        $this->post('/sair');

        $this->post('/login', ['email' => $u->email, 'password' => 'password']);
        $this->post('/login/dois-fatores', ['codigo' => $codigo])->assertSessionHasErrors('codigo');
        $this->assertGuest();
    }

    public function test_codigo_de_recuperacao_entra_uma_vez_so(): void
    {
        $u = $this->contaCom2fa(['ABCDE-FGHJK', 'MNPQR-STUVW']);

        $this->post('/login', ['email' => $u->email, 'password' => 'password']);
        $this->post('/login/dois-fatores', ['codigo' => 'abcde fghjk'])->assertRedirect(route('painel'));
        $this->assertAuthenticatedAs($u);
        $this->assertCount(1, $u->fresh()->dois_fatores_recuperacao);
        $this->post('/sair');

        $this->post('/login', ['email' => $u->email, 'password' => 'password']);
        $this->post('/login/dois-fatores', ['codigo' => 'ABCDE-FGHJK'])->assertSessionHasErrors('codigo');
        $this->assertGuest();
    }

    public function test_muitos_codigos_errados_bloqueiam(): void
    {
        $u = $this->contaCom2fa();
        $this->post('/login', ['email' => $u->email, 'password' => 'password']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/dois-fatores', ['codigo' => '000000']);
        }

        $this->post('/login/dois-fatores', ['codigo' => $this->codigoAgora()])
            ->assertSessionHasErrors(['codigo' => 'Muitos códigos errados. Tente de novo em 1 min.']);
        $this->assertGuest();
    }

    public function test_o_tempo_para_digitar_o_codigo_expira(): void
    {
        $u = $this->contaCom2fa();
        $this->post('/login', ['email' => $u->email, 'password' => 'password']);

        $this->travel(6)->minutes();
        $this->post('/login/dois-fatores', ['codigo' => $this->codigoAgora()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_ativar_pela_pagina_de_seguranca_e_desativar(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/seguranca')->assertOk()->assertSee('Ativar verificação em duas etapas');

        $this->actingAs($u)->post('/seguranca/dois-fatores/iniciar')->assertRedirect(route('seguranca'));
        $segredo = session('dois_fatores_novo');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $segredo);
        $this->actingAs($u)->get('/seguranca')->assertOk()->assertSee('qr-2fa', false)->assertSee('otpauth://totp/', false);

        // senha errada e código errado não ativam
        $codigo = Totp::codigo($segredo, Totp::passoAtual());
        $this->actingAs($u)->post('/seguranca/dois-fatores/confirmar', ['password' => 'errada', 'codigo' => $codigo])->assertSessionHasErrors('password');
        $this->actingAs($u)->post('/seguranca/dois-fatores/confirmar', ['password' => 'password', 'codigo' => '000000'])->assertSessionHasErrors('codigo');
        $this->assertFalse($u->fresh()->temDoisFatores());

        $r = $this->actingAs($u)->post('/seguranca/dois-fatores/confirmar', ['password' => 'password', 'codigo' => $codigo]);
        $r->assertRedirect(route('seguranca'))->assertSessionHas('codigos_recuperacao');
        $codigos = session('codigos_recuperacao');
        $this->assertCount(8, $codigos);
        $u = $u->fresh();
        $this->assertTrue($u->temDoisFatores());
        $this->assertSame($segredo, $u->dois_fatores_segredo);
        // guardado criptografado: o segredo e os códigos não aparecem no banco
        $linha = \DB::table('users')->where('id', $u->id)->first();
        $this->assertStringNotContainsString($segredo, $linha->dois_fatores_segredo);
        $this->assertStringNotContainsString($codigos[0], $linha->dois_fatores_recuperacao);
        $this->assertArrayNotHasKey('dois_fatores_segredo', $u->toArray());

        // códigos novos com a senha
        $this->actingAs($u)->post('/seguranca/dois-fatores/codigos', ['password' => 'password'])->assertSessionHas('codigos_recuperacao');
        $novos = session('codigos_recuperacao');
        $this->assertNotEquals($codigos, $novos);

        // desativar pede senha + código (de recuperação aqui); os antigos não valem mais
        $this->actingAs($u)->post('/seguranca/dois-fatores/desativar', ['password' => 'password', 'codigo' => '111111'])->assertSessionHasErrors('codigo');
        $this->actingAs($u)->post('/seguranca/dois-fatores/desativar', ['password' => 'password', 'codigo' => $codigos[0]])->assertSessionHasErrors('codigo');
        $this->actingAs($u)->post('/seguranca/dois-fatores/desativar', ['password' => 'password', 'codigo' => $novos[0]])
            ->assertRedirect(route('seguranca'));
        $this->assertFalse($u->fresh()->temDoisFatores());
    }

    public function test_admin_sem_2fa_ve_o_lembrete(): void
    {
        $admin = User::factory()->create(['email' => User::ADMINS[0]]);
        $comum = User::factory()->create();

        $this->actingAs($admin)->get('/reclame-aqui')->assertOk()->assertSee('data-aviso-2fa', false);
        $this->actingAs($admin)->get('/seguranca')->assertOk()->assertSee('Conta de administração');
        $this->actingAs($comum)->get('/reclame-aqui')->assertOk()->assertDontSee('data-aviso-2fa', false);

        $admin->forceFill(['dois_fatores_segredo' => self::SEGREDO, 'dois_fatores_ativo_em' => now()])->save();
        $this->actingAs($admin->fresh())->get('/reclame-aqui')->assertOk()->assertDontSee('data-aviso-2fa', false);
    }
}
