<?php

namespace App\Http\Controllers;

use App\Models\NegocioDado;
use App\Models\ZapConfig;
use App\Models\ZapConversa;
use App\Models\ZapMensagem;
use App\Support\Compartilhar;
use Illuminate\Http\Client\Response as RespostaHttp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * WhatsApp oficial (WhatsApp Cloud API da Meta) em Negócios › Clientes.
 * O dono da área guarda o id do número, o token de acesso (criptografado) e um token de verificação do webhook.
 * O webhook público (/whatsapp/webhook, sem CSRF, assinatura X-Hub-Signature-256 conferida com o segredo do app)
 * recebe as mensagens e os status; cada telefone vira uma conversa, ligada ao cliente com o mesmo número.
 * Responder: texto livre até 24 h depois da última mensagem do cliente; fora disso, só modelos aprovados.
 * Sem configuração, a tela continua com as mensagens prontas por wa.me.
 */
class ZapController extends Controller
{
    private const AREA = 'negocios';

    private static function graph(): string
    {
        return 'https://graph.facebook.com/' . config('services.meta.versao', 'v21.0') . '/';
    }

    /* ================= tela (logado) ================= */

    /** GET /negocios/whatsapp: situação da configuração e lista de conversas. */
    public function estado(Request $request): JsonResponse
    {
        $donoId = Compartilhar::dono($request, self::AREA);
        $cfg = ZapConfig::where('user_id', $donoId)->first();
        $eDono = $donoId === $request->user()->id;

        $falta = [];
        if (! $cfg) $falta[] = 'o id do número e o token de acesso do WhatsApp';
        elseif (! $cfg->segredo()) $falta[] = 'o segredo do app da Meta (para conferir o webhook)';

        return response()->json([
            'ok'          => true,
            'configurado' => (bool) $cfg,
            'falta'       => $falta,
            'eDono'       => $eDono,
            'config'      => $cfg ? [
                'phone_number_id' => $cfg->phone_number_id,
                'waba_id'         => $cfg->waba_id,
                'numero'          => $cfg->numero,
                'temSegredo'      => (bool) $cfg->app_secret,
                'segredoDoSite'   => ! $cfg->app_secret && (bool) config('services.meta.secret'),
                'verify_token'    => $eDono ? $cfg->verify_token : null,
                'ultimoAviso'     => $cfg->ultimo_aviso_em?->toIso8601String(),
            ] : null,
            'webhook'     => route('zap.webhook'),
            'conversas'   => $cfg ? ZapConversa::where('user_id', $donoId)->orderByDesc('ultima_em')->limit(300)->get()->map(fn ($c) => self::conversaJs($c))->values() : [],
        ]);
    }

    /** POST /negocios/whatsapp/config: só o dono. Token e segredo em branco mantêm os que já estavam guardados. */
    public function salvarConfig(Request $request): JsonResponse
    {
        self::abrirB64($request);
        $donoId = Compartilhar::dono($request, self::AREA);
        abort_unless($donoId === $request->user()->id, 403, 'Só o dono da área Negócios configura o WhatsApp.');
        $d = $request->validate([
            'phone_number_id' => ['required', 'regex:/^\d{5,30}$/'],
            'waba_id'         => ['nullable', 'regex:/^\d{5,30}$/'],
            'access_token'    => ['nullable', 'string', 'min:20', 'max:1000'],
            'app_secret'      => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{16,64}$/'],
            'verify_token'    => ['nullable', 'string', 'regex:/^[A-Za-z0-9._-]{8,100}$/'],
            'tirar_segredo'   => ['nullable', 'boolean'],
        ], [
            'phone_number_id.*' => 'Cole o "Identificação do número de telefone" (só números) da tela da API do WhatsApp.',
            'waba_id.*'         => 'O id da conta do WhatsApp Business é só números.',
            'access_token.*'    => 'Cole o token de acesso inteiro.',
            'app_secret.*'      => 'O segredo do app são letras e números (Configurações do app › Básico › Chave secreta).',
            'verify_token.*'    => 'O token de verificação usa letras, números, ponto, traço ou sublinhado (8 a 100).',
        ]);

        $cfg = ZapConfig::firstOrNew(['user_id' => $donoId]);
        if (! $cfg->exists && empty($d['access_token'])) {
            return response()->json(['ok' => false, 'erro' => 'Cole o token de acesso do WhatsApp.'], 422);
        }
        $cfg->phone_number_id = $d['phone_number_id'];
        $cfg->waba_id = $d['waba_id'] ?? null;
        if (! empty($d['access_token'])) $cfg->access_token = trim($d['access_token']);
        if (! empty($d['app_secret'])) $cfg->app_secret = $d['app_secret'];
        if ($request->boolean('tirar_segredo')) $cfg->app_secret = null;
        $cfg->verify_token = $d['verify_token'] ?? ($cfg->verify_token ?: 'wf-' . Str::lower(Str::random(24)));

        // confere o número na Meta (e guarda como ele aparece); erro aqui não impede salvar
        $aviso = null;
        $r = rescue(fn () => Http::withToken($cfg->access_token)->timeout(15)->get(self::graph() . $cfg->phone_number_id, ['fields' => 'display_phone_number,verified_name']), null, false);
        if ($r && $r->successful()) {
            $cfg->numero = trim(($r->json('display_phone_number') ?? '') . ' ' . ($r->json('verified_name') ? '· ' . $r->json('verified_name') : ''));
        } else {
            $aviso = 'Salvo, mas a Meta não confirmou o número com esse token' . ($r ? ' (' . self::erroMeta($r) . ')' : '') . '. Confira o id do número e o token.';
        }
        $cfg->save();
        Cache::forget('zap.modelos.' . $cfg->id);

        return response()->json(['ok' => true, 'aviso' => $aviso]);
    }

    /** DELETE /negocios/whatsapp/config: só o dono. As conversas ficam guardadas. */
    public function removerConfig(Request $request): JsonResponse
    {
        $donoId = Compartilhar::dono($request, self::AREA);
        abort_unless($donoId === $request->user()->id, 403, 'Só o dono da área Negócios configura o WhatsApp.');
        ZapConfig::where('user_id', $donoId)->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /negocios/whatsapp/conversa?tel=: mensagens com esse telefone (e marca como lidas). */
    public function conversa(Request $request): JsonResponse
    {
        $donoId = Compartilhar::dono($request, self::AREA);
        $c = $this->acharConversa($donoId, (string) $request->query('tel'));
        if (! $c) {
            return response()->json(['ok' => true, 'conversa' => null, 'mensagens' => []]);
        }
        if ($c->nao_lidas && ! $request->attributes->get('wf_so_ver')) $c->update(['nao_lidas' => 0]);
        $msgs = $c->mensagens()->orderByDesc('em')->orderByDesc('id')->limit(200)->get()->reverse()->values();

        return response()->json([
            'ok'        => true,
            'conversa'  => self::conversaJs($c),
            'mensagens' => $msgs->map(fn ($m) => [
                'id' => $m->id, 'direcao' => $m->direcao, 'tipo' => $m->tipo, 'texto' => $m->texto,
                'status' => $m->status, 'erro' => $m->erro, 'em' => $m->em->toIso8601String(),
            ]),
        ]);
    }

    /** GET /negocios/whatsapp/modelos: modelos aprovados da conta (para falar fora da janela de 24 h). */
    public function modelos(Request $request): JsonResponse
    {
        $cfg = ZapConfig::where('user_id', Compartilhar::dono($request, self::AREA))->first();
        if (! $cfg) return response()->json(['ok' => false, 'erro' => 'O WhatsApp oficial ainda não foi configurado.'], 409);
        if (! $cfg->waba_id) return response()->json(['ok' => false, 'erro' => 'Para usar modelos, preencha o id da conta do WhatsApp Business na configuração.'], 409);

        $lista = Cache::remember('zap.modelos.' . $cfg->id, now()->addMinutes(10), function () use ($cfg) {
            $r = Http::withToken($cfg->access_token)->timeout(15)->get(self::graph() . $cfg->waba_id . '/message_templates', [
                'fields' => 'name,language,status,components', 'status' => 'APPROVED', 'limit' => 100,
            ]);
            if (! $r->successful()) return ['erro' => self::erroMeta($r)];

            return collect($r->json('data') ?? [])->filter(fn ($t) => ($t['status'] ?? '') === 'APPROVED')->map(function ($t) {
                $corpo = collect($t['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? '';
                preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $corpo, $m);

                return ['nome' => $t['name'], 'idioma' => $t['language'] ?? 'pt_BR', 'texto' => $corpo, 'params' => count(array_unique($m[1]))];
            })->values()->all();
        });
        if (isset($lista['erro'])) {
            Cache::forget('zap.modelos.' . $cfg->id);

            return response()->json(['ok' => false, 'erro' => 'A Meta recusou a leitura dos modelos: ' . $lista['erro'] . '.'], 422);
        }

        return response()->json(['ok' => true, 'modelos' => $lista]);
    }

    /** POST /negocios/whatsapp/enviar: texto (janela de 24 h aberta) ou modelo aprovado. */
    public function enviar(Request $request): JsonResponse
    {
        self::abrirB64($request);
        $donoId = Compartilhar::dono($request, self::AREA);
        $d = $request->validate([
            'tel'        => ['required', 'string', 'max:30'],
            'texto'      => ['nullable', 'string', 'max:4096', 'required_without:modelo'],
            'modelo'     => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'idioma'     => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z_]{2,15}$/'],
            'params'     => ['nullable', 'array', 'max:20'],
            'params.*'   => ['nullable', 'string', 'max:1000'],
            'nome'       => ['nullable', 'string', 'max:150'],
            'cliente_id' => ['nullable', 'string', 'max:40'],
        ]);
        $cfg = ZapConfig::where('user_id', $donoId)->first();
        if (! $cfg) return response()->json(['ok' => false, 'erro' => 'O WhatsApp oficial ainda não foi configurado. Use "Abrir no WhatsApp".'], 409);

        $tel = ZapConversa::normalizar($d['tel']);
        if (strlen($tel) < 10) return response()->json(['ok' => false, 'erro' => 'Telefone inválido.'], 422);

        $c = $this->acharConversa($donoId, $tel) ?? new ZapConversa(['user_id' => $donoId, 'telefone' => $tel, 'chave' => ZapConversa::chave($tel), 'nao_lidas' => 0]);
        if (! $c->cliente_id && ! empty($d['cliente_id'])) $c->cliente_id = $d['cliente_id'];
        if (! $c->nome && ! empty($d['nome'])) $c->nome = mb_substr($d['nome'], 0, 150);

        $modelo = $d['modelo'] ?? null;
        if (! $modelo && ! $c->janelaAberta()) {
            return response()->json(['ok' => false, 'janela' => false, 'erro' => 'Já passaram 24 horas desde a última mensagem do cliente (ou ele nunca escreveu). O WhatsApp só deixa mandar um modelo aprovado agora.'], 422);
        }

        if ($modelo) {
            $params = array_values(array_filter($d['params'] ?? [], fn ($p) => $p !== null && $p !== ''));
            $corpo = ['type' => 'template', 'template' => array_filter([
                'name' => $modelo, 'language' => ['code' => $d['idioma'] ?? 'pt_BR'],
                'components' => $params ? [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params)]] : null,
            ])];
            $texto = 'Modelo "' . $modelo . '"' . ($params ? ': ' . implode(' · ', $params) : '');
        } else {
            $corpo = ['type' => 'text', 'text' => ['body' => $d['texto'], 'preview_url' => true]];
            $texto = $d['texto'];
        }

        $r = Http::withToken($cfg->access_token)->timeout(20)->asJson()->post(self::graph() . $cfg->phone_number_id . '/messages', [
            'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $c->exists ? $c->telefone : $tel,
        ] + $corpo);

        if (! $r->successful() || ! $r->json('messages.0.id')) {
            $codigo = (int) $r->json('error.code');
            $erro = $codigo === 131047 ? 'Já passaram 24 horas desde a última mensagem do cliente: mande um modelo aprovado.' : 'A Meta não enviou: ' . self::erroMeta($r) . '.';

            return response()->json(['ok' => false, 'janela' => $codigo === 131047 ? false : null, 'erro' => $erro], 422);
        }

        $c->fill(['ultima_em' => now(), 'previa' => mb_substr('Você: ' . $texto, 0, 160)])->save();
        $m = $c->mensagens()->create([
            'direcao' => 'out', 'tipo' => $modelo ? 'template' : 'text', 'texto' => mb_substr($texto, 0, 4096),
            'wamid' => $r->json('messages.0.id'), 'status' => 'sent', 'autor_id' => $request->user()->id, 'em' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $m->id, 'conversa' => self::conversaJs($c->fresh())]);
    }

    /* ================= webhook (público) ================= */

    /** GET /whatsapp/webhook: a Meta confere o endereço com o token de verificação. */
    public function verificar(Request $request): Response
    {
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $ok = $request->query('hub_mode', $request->query('hub.mode')) === 'subscribe' && $token !== ''
            && ZapConfig::where('verify_token', $token)->exists();

        return $ok ? response((string) $request->query('hub_challenge', $request->query('hub.challenge', '')), 200, ['Content-Type' => 'text/plain'])
                   : response('Token de verificação não confere.', 403);
    }

    /** POST /whatsapp/webhook: mensagens recebidas e status das enviadas. */
    public function receber(Request $request): JsonResponse
    {
        $corpo = (string) $request->getContent();
        $json = json_decode($corpo, true);
        if (! is_array($json)) return response()->json(['ok' => false], 400);

        $phoneId = (string) data_get($json, 'entry.0.changes.0.value.metadata.phone_number_id', '');
        $cfg = $phoneId !== '' ? ZapConfig::where('phone_number_id', $phoneId)->first() : null;
        $segredo = $cfg ? $cfg->segredo() : (string) config('services.meta.secret');
        if ($segredo === '') return response()->json(['ok' => false, 'erro' => 'sem segredo'], 503);

        $assinatura = (string) $request->header('X-Hub-Signature-256');
        if (! hash_equals('sha256=' . hash_hmac('sha256', $corpo, $segredo), $assinatura)) {
            return response()->json(['ok' => false, 'erro' => 'assinatura inválida'], 401);
        }
        if (! $cfg) return response()->json(['ok' => true, 'ignorado' => true]); // número que não é de ninguém aqui

        $cfg->forceFill(['ultimo_aviso_em' => now()])->save();
        foreach ($json['entry'] ?? [] as $entrada) {
            foreach ($entrada['changes'] ?? [] as $mudanca) {
                $v = $mudanca['value'] ?? [];
                if ((string) data_get($v, 'metadata.phone_number_id') !== $cfg->phone_number_id) continue;
                $nomes = collect($v['contacts'] ?? [])->mapWithKeys(fn ($c) => [(string) ($c['wa_id'] ?? '') => (string) data_get($c, 'profile.name', '')]);
                foreach ($v['messages'] ?? [] as $m) $this->guardarRecebida($cfg->user_id, $m, $nomes);
                foreach ($v['statuses'] ?? [] as $s) $this->guardarStatus($s);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function guardarRecebida(int $donoId, array $m, $nomes): void
    {
        $wamid = (string) ($m['id'] ?? '');
        $tel = preg_replace('/\D/', '', (string) ($m['from'] ?? ''));
        if ($tel === '' || ($wamid !== '' && ZapMensagem::where('wamid', $wamid)->exists())) return; // a Meta repete avisos

        $tipo = (string) ($m['type'] ?? 'text');
        $texto = match ($tipo) {
            'text'        => data_get($m, 'text.body'),
            'button'      => data_get($m, 'button.text'),
            'interactive' => data_get($m, 'interactive.button_reply.title') ?? data_get($m, 'interactive.list_reply.title'),
            'image'       => trim('[imagem] ' . data_get($m, 'image.caption', '')),
            'video'       => trim('[vídeo] ' . data_get($m, 'video.caption', '')),
            'document'    => trim('[documento] ' . data_get($m, 'document.filename', '')),
            'audio'       => '[áudio]',
            'sticker'     => '[figurinha]',
            'location'    => '[localização] ' . data_get($m, 'location.latitude') . ', ' . data_get($m, 'location.longitude'),
            'reaction'    => '[reação ' . data_get($m, 'reaction.emoji', '') . ']',
            default       => '[' . $tipo . ']',
        };
        $em = isset($m['timestamp']) ? now()->setTimestamp((int) $m['timestamp']) : now();

        $c = $this->acharConversa($donoId, $tel) ?? new ZapConversa(['user_id' => $donoId, 'telefone' => $tel, 'chave' => ZapConversa::chave($tel), 'nao_lidas' => 0]);
        if (! $c->cliente_id) {
            $cli = self::clientePorTelefone($donoId, $tel);
            if ($cli) { $c->cliente_id = $cli['id']; $c->nome = $c->nome ?: $cli['nome']; }
        }
        if (! $c->nome && ($nomes[$tel] ?? '') !== '') $c->nome = mb_substr($nomes[$tel], 0, 150);
        $c->telefone = $tel; // guarda como o WhatsApp escreve (é para esse número que a resposta vai)
        $c->fill(['ultima_entrada_em' => $em, 'ultima_em' => $em, 'previa' => mb_substr((string) $texto, 0, 160), 'nao_lidas' => $c->nao_lidas + 1])->save();
        $c->mensagens()->create(['direcao' => 'in', 'tipo' => $tipo, 'texto' => mb_substr((string) $texto, 0, 4096), 'wamid' => $wamid ?: null, 'status' => 'recebida', 'em' => $em]);
    }

    private function guardarStatus(array $s): void
    {
        $msg = ZapMensagem::where('wamid', (string) ($s['id'] ?? ''))->first();
        if (! $msg) return;
        $ordem = ['sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        $novo = (string) ($s['status'] ?? '');
        if (($ordem[$novo] ?? 0) <= ($ordem[$msg->status] ?? 0) && $novo !== 'failed') return; // avisos chegam fora de ordem
        $msg->update(['status' => $novo, 'erro' => $novo === 'failed' ? mb_substr((string) (data_get($s, 'errors.0.title') ?: data_get($s, 'errors.0.message') ?: 'Falhou'), 0, 300) : null]);
    }

    /* ================= ajudantes ================= */

    /**
     * A tela manda o corpo como {"b64": base64(JSON)}: o mod_security da HostGator responde 406 quando o texto
     * de uma mensagem parece SQL ou código. Aqui o JSON é aberto e vira os campos normais da requisição.
     */
    private static function abrirB64(Request $request): void
    {
        $b64 = $request->input('b64');
        if (! is_string($b64) || $b64 === '') return;
        $json = json_decode((string) base64_decode($b64, true), true);
        abort_unless(is_array($json), 422, 'Os dados enviados vieram corrompidos. Tente de novo.');
        $request->merge($json);
        $request->offsetUnset('b64');
    }

    private function acharConversa(int $donoId, string $tel): ?ZapConversa
    {
        $tel = ZapConversa::normalizar($tel);
        if (strlen($tel) < 10) return null;

        return ZapConversa::where('user_id', $donoId)->where('telefone', $tel)->first()
            ?? ZapConversa::where('user_id', $donoId)->where('chave', ZapConversa::chave($tel))->orderByDesc('ultima_em')->first();
    }

    /** Cliente da área Negócios com esse telefone no contato (estado guardado em negocio_dados). */
    public static function clientePorTelefone(int $donoId, string $tel): ?array
    {
        $bruto = NegocioDado::where('user_id', $donoId)->where('chave', 'estado')->value('valor');
        $e = is_string($bruto) ? json_decode($bruto, true) : null;
        $chave = ZapConversa::chave($tel);
        foreach ((is_array($e) ? ($e['clientes'] ?? []) : []) as $c) {
            $contato = (string) ($c['contato'] ?? '');
            if ($contato === '' || str_contains($contato, '@') || strlen(preg_replace('/\D/', '', $contato)) < 10) continue;
            if (ZapConversa::chave($contato) === $chave) return ['id' => (string) ($c['id'] ?? ''), 'nome' => (string) ($c['nome'] ?? '')];
        }

        return null;
    }

    private static function conversaJs(ZapConversa $c): array
    {
        return [
            'id' => $c->id, 'telefone' => $c->telefone, 'chave' => $c->chave, 'nome' => $c->nome, 'cliente_id' => $c->cliente_id,
            'previa' => $c->previa, 'ultima_em' => $c->ultima_em?->toIso8601String(), 'nao_lidas' => (int) $c->nao_lidas,
            'janela' => $c->janelaAberta(), 'janela_ate' => $c->ultima_entrada_em?->copy()->addHours(24)->toIso8601String(),
        ];
    }

    private static function erroMeta(RespostaHttp $r): string
    {
        $codigo = (int) $r->json('error.code');

        return match (true) {
            $codigo === 190                       => 'o token de acesso venceu ou foi recusado',
            in_array($codigo, [131030], true)     => 'o número não está na lista de teste do app',
            in_array($codigo, [131026], true)     => 'o número não tem WhatsApp ou não pode receber',
            in_array($codigo, [132000, 132001], true) => 'o modelo ou os campos dele não conferem',
            default                               => mb_substr((string) ($r->json('error.error_data.details') ?: $r->json('error.message') ?: 'erro ' . $r->status()), 0, 200),
        };
    }
}
