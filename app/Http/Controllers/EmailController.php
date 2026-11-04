<?php

namespace App\Http\Controllers;

use App\Models\EmailConta;
use App\Models\EmailMensagem;
use App\Models\EmailRegra;
use App\Support\Emails\Classificador;
use App\Support\Emails\EmailErro;
use App\Support\Emails\Google;
use App\Support\Emails\LeitorEmails;
use App\Support\Emails\Sincronizar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Negócios › E-mails: a pessoa conecta a própria caixa (IMAP com senha, ou Gmail pelo Google), o site lê as
 * mensagens recentes, separa por categoria com as regras dela e mostra com busca. Cada conta é só do dono:
 * não entra no compartilhamento da área. Senha e tokens ficam criptografados e nunca voltam para a tela.
 */
class EmailController extends Controller
{
    public const MAX_CONTAS = 3;

    /** O site está com APP_LOCALE=en: as mensagens de validação desta tela vão em português aqui. */
    private const MENSAGENS = [
        'required'                  => 'Preencha este campo.',
        'endereco.required'         => 'Escreva o e-mail da caixa.',
        'endereco.email'            => 'Esse e-mail não parece válido.',
        'senha.required'            => 'Escreva a senha da caixa.',
        'host.required'             => 'Escreva o servidor IMAP (ex.: imap.titan.email).',
        'max'                       => 'Texto comprido demais (até :max caracteres).',
        'regras.*.categoria.required' => 'Toda regra precisa de uma categoria.',
        'regras.*.categoria.max'    => 'Nome de categoria comprido demais (até 40 caracteres).',
        'regras.*.termo.required'   => 'Toda regra precisa de uma palavra.',
        'regras.*.termo.max'        => 'Palavra da regra comprida demais (até 120 caracteres).',
        'regras.max'                => 'No máximo 200 regras.',
        'categoria.max'             => 'Nome de categoria comprido demais (até 40 caracteres).',
        'in'                        => 'Opção inválida.',
    ];

    public function show(Request $request): View
    {
        return view('areas.negocios.emails', [
            'estado' => $this->estado($request->user()->id),
            'google' => Google::configurado(),
            'envio'  => $request->user()->eAdmin() ? self::envio() : null,
        ]);
    }

    /* ---------------- envio do site (só para a administração conferir SMTP e cron) ---------------- */

    /** Como o site manda e-mails (sem usuário nem senha) e quando o agendador rodou por último. */
    public static function envio(): array
    {
        $mailer = (string) config('mail.default');
        $cfg = (array) config("mail.mailers.{$mailer}", []);
        $ultimo = fn (string $cmd) => \App\Support\Agendador::ultimo($cmd)?->toIso8601String();

        return [
            'mailer'     => $mailer,
            'so_log'     => in_array($cfg['transport'] ?? $mailer, ['log', 'array'], true),
            'host'       => $cfg['host'] ?? null,
            'porta'      => $cfg['port'] ?? null,
            'seguranca'  => $cfg['scheme'] ?? ($cfg['encryption'] ?? null),
            'remetente'  => config('mail.from.address'),
            'calendario' => $ultimo('calendario:lembrar'),
            'emails'     => $ultimo('emails:sincronizar'),
        ];
    }

    /** POST /negocios/emails/teste-envio: manda um e-mail curto para o próprio endereço e devolve o erro exato do SMTP. */
    public function testeEnvio(Request $request): JsonResponse
    {
        $u = $request->user();
        abort_unless($u->eAdmin(), 404);
        $info = self::envio();

        try {
            \Illuminate\Support\Facades\Mail::to($u->email, $u->name)
                ->send(new \App\Mail\TesteEnvio((string) $u->name, now()->format('d/m/Y H:i')));
        } catch (\Throwable $e) {
            report($e);
            $msg = $e->getMessage();
            foreach (['password', 'username'] as $campo) {
                $segredo = (string) config("mail.mailers.{$info['mailer']}.{$campo}");
                if ($segredo !== '') $msg = str_replace($segredo, '***', $msg);
            }

            return response()->json(['erro' => 'O servidor de e-mail recusou o envio: ' . mb_substr(trim($msg), 0, 500)] + ['envio' => $info], 422);
        }

        $ok = $info['so_log']
            ? "Envio simulado: o site está com MAIL_MAILER={$info['mailer']}, então o e-mail foi só para o log (storage/logs) e não sai de verdade."
            : "E-mail de teste enviado para {$u->email} por {$info['host']}:{$info['porta']}. Confira a caixa de entrada e o spam.";

        return response()->json(['ok' => $ok, 'envio' => $info]);
    }

    /**
     * O navegador manda os formulários como {"b64": base64(JSON)}: o mod_security da HostGator responde 406
     * quando um corpo parece SQL ou código (uma senha ou regra com aspas e ponto e vírgula basta).
     */
    private function abrirCorpo(Request $request): void
    {
        $b64 = $request->input('b64');
        if (! is_string($b64)) return;
        $dados = json_decode((string) base64_decode($b64, true), true);
        abort_unless(is_array($dados), 422, 'Corpo inválido.');
        $request->request->remove('b64');
        $request->json()->remove('b64');
        $request->merge($dados);
    }

    /** POST /negocios/emails/contas: confere o login na caixa antes de guardar e já faz a primeira leitura. */
    public function conectar(Request $request, LeitorEmails $leitor): JsonResponse
    {
        $this->abrirCorpo($request);
        $uid = $request->user()->id;
        $d = $request->validate([
            'endereco'  => ['required', 'email', 'max:190'],
            'host'      => ['required', 'string', 'max:190', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'porta'     => ['required', 'integer', Rule::in([993, 143])],
            'seguranca' => ['required', Rule::in(['ssl', 'starttls'])],
            'usuario'   => ['nullable', 'string', 'max:190'],
            'senha'     => ['required', 'string', 'max:500'],
        ], self::MENSAGENS + [
            'host.regex' => 'Servidor inválido. Use um nome como imap.titan.email.',
            'porta.in'   => 'Use a porta 993 (SSL) ou 143 (STARTTLS).',
        ]);
        $endereco = mb_strtolower(trim($d['endereco']));

        $existente = EmailConta::where('user_id', $uid)->where('endereco', $endereco)->where('tipo', 'imap')->first();
        if (! $existente && EmailConta::where('user_id', $uid)->count() >= self::MAX_CONTAS) {
            return response()->json(['erro' => 'Dá para conectar até ' . self::MAX_CONTAS . ' caixas. Remova uma para conectar outra.'], 422);
        }

        $conta = $existente ?? new EmailConta(['user_id' => $uid, 'tipo' => 'imap']);
        $conta->fill([
            'endereco'  => $endereco,
            'host'      => mb_strtolower(trim($d['host'])),
            'porta'     => (int) $d['porta'],
            'seguranca' => $d['seguranca'],
            'usuario'   => trim((string) ($d['usuario'] ?? '')) ?: $endereco,
            'senha'     => $d['senha'],
        ]);
        $conta->user_id = $uid;

        try {
            $leitor->testar($conta);
        } catch (EmailErro $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
        $conta->forceFill(['erro' => null])->save();

        $aviso = 'Caixa conectada.';
        try {
            $n = Sincronizar::conta($conta);
            $aviso .= ' ' . ($n === 1 ? '1 mensagem nova.' : "{$n} mensagens novas.");
        } catch (EmailErro $e) {
            $aviso .= ' A primeira leitura falhou: ' . $e->getMessage();
        }

        return response()->json(['ok' => $aviso] + $this->estado($uid));
    }

    public function remover(Request $request, EmailConta $conta): JsonResponse
    {
        abort_unless($conta->user_id === $request->user()->id, 404);
        $conta->delete(); // as mensagens saem junto (cascade)

        return response()->json(['ok' => 'Caixa desconectada. As mensagens dela saíram daqui (continuam no seu e-mail).'] + $this->estado($request->user()->id));
    }

    /** POST /negocios/emails/sincronizar: lê de novo todas as caixas da pessoa (ou só a de "conta"). */
    public function sincronizar(Request $request): JsonResponse
    {
        $this->abrirCorpo($request);
        $uid = $request->user()->id;
        $d = $request->validate(['conta' => ['nullable', 'integer']]);
        $contas = EmailConta::where('user_id', $uid)->when($d['conta'] ?? null, fn ($q, $id) => $q->where('id', $id))->get();
        if ($contas->isEmpty()) return response()->json(['erro' => 'Nenhuma caixa conectada.'] + $this->estado($uid), 422);

        $novas = 0;
        $erros = [];
        foreach ($contas as $c) {
            try {
                $novas += Sincronizar::conta($c);
            } catch (EmailErro $e) {
                $erros[] = $c->endereco . ': ' . $e->getMessage();
            }
        }

        $todasFalharam = count($erros) === $contas->count();

        return response()->json([
            'ok'   => $todasFalharam ? null : ($novas === 0 ? 'Nenhuma mensagem nova.' : ($novas === 1 ? '1 mensagem nova.' : "{$novas} mensagens novas.")),
            'erro' => $erros ? implode(' · ', $erros) . ' Use “Trocar senha” na caixa para corrigir.' : null,
        ] + $this->estado($uid));
    }

    /** PUT /negocios/emails/regras: troca todas as regras da pessoa e reaplica nas mensagens (menos as mudadas à mão). */
    public function regras(Request $request): JsonResponse
    {
        $this->abrirCorpo($request);
        $uid = $request->user()->id;
        $d = $request->validate([
            'regras'             => ['present', 'array', 'max:200'],
            'regras.*.categoria' => ['required', 'string', 'max:40'],
            'regras.*.campo'     => ['required', Rule::in(['remetente', 'assunto', 'qualquer'])],
            'regras.*.termo'     => ['required', 'string', 'max:120'],
        ], self::MENSAGENS);

        EmailRegra::where('user_id', $uid)->delete();
        $lista = array_values(array_filter($d['regras'], fn ($r) => trim($r['termo']) !== '' && trim($r['categoria']) !== ''));
        foreach ($lista as $i => $r) {
            EmailRegra::create(['user_id' => $uid, 'categoria' => Str::limit(trim($r['categoria']), 40, ''), 'campo' => $r['campo'], 'termo' => trim($r['termo']), 'ordem' => $i]);
        }
        if (! $lista) EmailRegra::create(['user_id' => $uid, 'categoria' => Classificador::PADRAO, 'campo' => 'qualquer', 'termo' => '', 'ordem' => 0]);

        $n = Sincronizar::reclassificar($uid);

        return response()->json(['ok' => 'Regras salvas.' . ($n ? " {$n} " . ($n === 1 ? 'mensagem mudou' : 'mensagens mudaram') . ' de categoria.' : '')] + $this->estado($uid));
    }

    /** PATCH /negocios/emails/mensagens/{mensagem}: categoria escolhida à mão ("auto" volta para as regras). */
    public function categoria(Request $request, EmailMensagem $mensagem): JsonResponse
    {
        $this->abrirCorpo($request);
        $uid = $request->user()->id;
        abort_unless($mensagem->user_id === $uid, 404);
        $d = $request->validate(['categoria' => ['required', 'string', 'max:40']], self::MENSAGENS);

        if ($d['categoria'] === 'auto') {
            $cat = Classificador::categoria(Classificador::regras($uid), Classificador::emailsDeClientes($uid), $mensagem->de_nome, $mensagem->de_email, $mensagem->assunto);
            $mensagem->forceFill(['categoria' => $cat, 'categoria_manual' => false])->save();
        } else {
            $mensagem->forceFill(['categoria' => trim($d['categoria']), 'categoria_manual' => true])->save();
        }

        return response()->json(['mensagem' => $mensagem->paraTela()]);
    }

    /* ---------------- Gmail pelo Google (fica pronto, desligado sem GOOGLE_CLIENT_ID) ---------------- */

    public function google(Request $request): RedirectResponse
    {
        if (! Google::configurado()) {
            return redirect()->route('emails')->with('emails_erro', 'O login pelo Google ainda não foi configurado neste Work Flower. Use o Gmail com senha de app.');
        }
        if (EmailConta::where('user_id', $request->user()->id)->count() >= self::MAX_CONTAS) {
            return redirect()->route('emails')->with('emails_erro', 'Dá para conectar até ' . self::MAX_CONTAS . ' caixas. Remova uma para conectar outra.');
        }
        $state = Str::random(40);
        $request->session()->put("emails_state.{$state}", now()->timestamp);

        return redirect()->away(Google::urlAutorizacao($state));
    }

    public function googleRetorno(Request $request, LeitorEmails $leitor): RedirectResponse
    {
        $uid = $request->user()->id;
        $state = (string) $request->query('state');
        $em = $state !== '' ? $request->session()->pull("emails_state.{$state}") : null;
        if (! $em || now()->timestamp - (int) $em > 1800) {
            return redirect()->route('emails')->with('emails_erro', 'A autorização expirou ou não veio deste navegador. Tente de novo.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('emails')->with('emails_erro', 'A autorização foi cancelada no Google.');
        }

        try {
            $t = Google::trocarCodigo((string) $request->query('code'));
        } catch (EmailErro $e) {
            return redirect()->route('emails')->with('emails_erro', $e->getMessage());
        }

        $conta = EmailConta::firstOrNew(['user_id' => $uid, 'tipo' => 'gmail', 'endereco' => $t['email']]);
        if (! $conta->exists && EmailConta::where('user_id', $uid)->count() >= self::MAX_CONTAS) {
            return redirect()->route('emails')->with('emails_erro', 'Dá para conectar até ' . self::MAX_CONTAS . ' caixas.');
        }
        $conta->forceFill([
            'access_token'    => $t['access_token'],
            'refresh_token'   => $t['refresh_token'] ?: $conta->refresh_token,
            'token_expira_em' => now()->addSeconds($t['expira']),
            'host'            => 'gmail.googleapis.com',
            'erro'            => null,
        ])->save();

        try {
            $n = Sincronizar::conta($conta);
            $msg = "Gmail conectado ({$t['email']}). " . ($n === 1 ? '1 mensagem nova.' : "{$n} mensagens novas.");
        } catch (EmailErro $e) {
            $msg = "Gmail conectado ({$t['email']}), mas a primeira leitura falhou: " . $e->getMessage();
        }

        return redirect()->route('emails')->with('emails_ok', $msg);
    }

    /* ---------------- dados para a tela ---------------- */

    private function estado(int $uid): array
    {
        $regras = Classificador::regras($uid);
        $categorias = collect(Classificador::CATEGORIAS)
            ->merge($regras->pluck('categoria'))
            ->merge(EmailMensagem::where('user_id', $uid)->distinct()->pluck('categoria'))
            ->filter()->unique()->values();

        return [
            'contas'     => EmailConta::where('user_id', $uid)->orderBy('id')->get()->map->paraTela()->values(),
            'mensagens'  => EmailMensagem::where('user_id', $uid)->orderByDesc('recebido_em')->limit(1000)->get()->map->paraTela()->values(),
            'regras'     => $regras->filter(fn ($r) => $r->termo !== '')->map(fn ($r) => ['categoria' => $r->categoria, 'campo' => $r->campo, 'termo' => $r->termo])->values(),
            'categorias' => $categorias,
        ];
    }
}
