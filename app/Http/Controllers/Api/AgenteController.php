<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Automacao;
use App\Models\AutomacaoDispositivo;
use App\Models\AutomacaoExecucao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API do agente do Windows (/api/agente/...). Só entrega e recebe execuções do próprio aparelho:
 * o agente não consegue ver nem mexer em nada mais da conta.
 */
class AgenteController extends Controller
{
    private function dispositivo(Request $request): AutomacaoDispositivo
    {
        return $request->attributes->get('dispositivo');
    }

    /** Teste de conexão do agente (usado na instalação). */
    public function ola(Request $request): JsonResponse
    {
        $d = $this->dispositivo($request);

        return response()->json([
            'ok'          => true,
            'dispositivo' => $d->nome,
            'usuario'     => $d->user?->name,
            'intervalo'   => 30,
            'acoes'       => array_keys(Automacao::ACOES),
        ]);
    }

    /** Execuções para rodar agora. Dispara antes os horários vencidos e expira o que ficou velho. */
    public function pendentes(Request $request): JsonResponse
    {
        $d = $this->dispositivo($request);

        Automacao::processarHorarios($d);

        AutomacaoExecucao::where('dispositivo_id', $d->id)->where('status', 'pendente')
            ->where('created_at', '<', now()->subHours(AutomacaoExecucao::HORAS_PARA_EXPIRAR))
            ->update(['status' => 'expirada', 'terminada_em' => now()]);
        // entregue e nunca respondida (agente fechado no meio): encerra para não ficar "rodando" para sempre
        AutomacaoExecucao::where('dispositivo_id', $d->id)->where('status', 'enviada')
            ->where('enviada_em', '<', now()->subHours(2))
            ->update(['status' => 'erro', 'resultado' => 'O agente não respondeu.', 'terminada_em' => now()]);

        $lista = AutomacaoExecucao::where('dispositivo_id', $d->id)->where('status', 'pendente')
            ->orderBy('id')->limit(20)->get();

        if ($lista->isNotEmpty()) {
            AutomacaoExecucao::whereIn('id', $lista->pluck('id'))->where('status', 'pendente')
                ->update(['status' => 'enviada', 'enviada_em' => now()]);
        }

        return response()->json([
            'execucoes' => $lista->map(fn (AutomacaoExecucao $e) => [
                'id'     => $e->id,
                'nome'   => $e->nome,
                'origem' => $e->origem,
                // ações também em base64 (JSON UTF-8): caminhos e textos chegam intactos mesmo com filtros no caminho
                'acoes'     => $e->acoes,
                'acoes_b64' => base64_encode(json_encode($e->acoes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ])->values(),
        ]);
    }

    /** Resultado de uma execução (ok, erro ou recusada pela pessoa no computador). */
    public function resultado(Request $request, int $execucao): JsonResponse
    {
        $d = $this->dispositivo($request);
        $e = AutomacaoExecucao::where('id', $execucao)->where('dispositivo_id', $d->id)->first();
        if (! $e) {
            return response()->json(['erro' => 'Execução não encontrada.'], 404);
        }

        $dados = $request->validate([
            'status'    => ['required', Rule::in(['ok', 'erro', 'recusada'])],
            'resultado' => ['nullable', 'string', 'max:4000'],
            // o agente manda o texto em base64: mensagens de erro com caminhos e comandos fazem o
            // mod_security da hospedagem responder 406
            'resultado_b64' => ['nullable', 'string', 'max:8000'],
        ]);
        if (! empty($dados['resultado_b64'])) {
            $txt = base64_decode($dados['resultado_b64'], true);
            if ($txt === false || ! mb_check_encoding($txt, 'UTF-8')) {
                return response()->json(['erro' => 'resultado_b64 inválido.'], 422);
            }
            $dados['resultado'] = mb_substr($txt, 0, 4000);
        }

        if (! in_array($e->status, ['pendente', 'enviada'], true)) {
            return response()->json(['erro' => 'Essa execução já foi encerrada.'], 409);
        }

        $e->update([
            'status'       => $dados['status'],
            'resultado'    => $dados['resultado'] ?? null,
            'terminada_em' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Textos rápidos da conta (os mesmos do expansor do site, js/expansor.js) para o agente expandir os atalhos
     * em qualquer programa do Windows. Só atalho e texto; "ligado" segue a chave do site (expansorLigado).
     * A lista vai em base64 (JSON UTF-8): textos com comandos ou HTML não esbarram no mod_security da hospedagem.
     */
    public function trechos(Request $request): JsonResponse
    {
        $d = $this->dispositivo($request);
        $prefs = \App\Http\Controllers\PreferenciasController::de($d->user_id);

        $lista = collect(is_array($prefs['trechos'] ?? null) ? $prefs['trechos'] : [])
            ->filter(fn ($t) => is_array($t) && ($t['atalho'] ?? '') !== '' && ($t['texto'] ?? '') !== '')
            ->map(fn ($t) => ['atalho' => (string) $t['atalho'], 'texto' => (string) $t['texto']])
            ->values()->all();
        $json = json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->json([
            'ligado'      => ($prefs['expansorLigado'] ?? true) !== false,
            'nome'        => (string) $d->user?->name,
            'email'       => (string) $d->user?->email,
            'quantidade'  => count($lista),
            'versao'      => substr(md5($json . '|' . (($prefs['expansorLigado'] ?? true) !== false ? 1 : 0)), 0, 12),
            'trechos_b64' => base64_encode($json),
        ]);
    }

    /** Pacote do agente (.zip) para o instalador do app (Windows) instalar sem a pessoa baixar nada à parte. */
    public function pacote(): \Illuminate\Http\Response|JsonResponse
    {
        $zip = \App\Support\PacoteAgente::gerar();
        if ($zip === null) {
            return response()->json(['erro' => 'Os arquivos do agente não estão no servidor.'], 503);
        }

        return response($zip, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="work-flower-agente.zip"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
