<?php

namespace App\Http\Controllers;

use App\Models\Automacao;
use App\Models\AutomacaoDispositivo;
use App\Models\AutomacaoExecucao;
use App\Models\Tarefa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Página "Automações": computadores pareados, automações (gatilho → ações) e histórico de execuções. */
class AutomacaoController extends Controller
{
    public const MAX_DISPOSITIVOS = 5;
    public const MAX_AUTOMACOES = 50;

    public function index(Request $request): View
    {
        $eu = $request->user()->id;

        return view('automacoes.index', [
            'dispositivos' => AutomacaoDispositivo::where('user_id', $eu)->orderByRaw('revogado_em is not null')->orderByDesc('id')->get(),
            'automacoes'   => Automacao::where('user_id', $eu)->with('dispositivo')->orderBy('nome')->get(),
            'execucoes'    => AutomacaoExecucao::where('user_id', $eu)->with('dispositivo')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /** Situação das execuções (a página confere de vez em quando, sem recarregar). */
    public function historico(Request $request): JsonResponse
    {
        $lista = AutomacaoExecucao::where('user_id', $request->user()->id)->orderByDesc('id')->limit(50)->get();

        return response()->json($lista->map(fn ($e) => [
            'id' => $e->id, 'status' => $e->status, 'rotulo' => AutomacaoExecucao::STATUS[$e->status] ?? $e->status,
            'resultado' => $e->resultado,
        ]));
    }

    public function salvar(Request $request, ?Automacao $automacao = null): RedirectResponse
    {
        $eu = $request->user()->id;
        if ($automacao) {
            abort_unless($automacao->user_id === $eu, 404);
        } elseif (Automacao::where('user_id', $eu)->count() >= self::MAX_AUTOMACOES) {
            return back()->withErrors(['nome' => 'Limite de ' . self::MAX_AUTOMACOES . ' automações.']);
        }

        $dados = $request->validate([
            'nome'           => ['required', 'string', 'max:80'],
            'dispositivo_id' => ['required', 'integer', Rule::exists('automacao_dispositivos', 'id')->where('user_id', $eu)->whereNull('revogado_em')],
            'gatilho'        => ['required', Rule::in(array_keys(Automacao::GATILHOS))],
            // a página manda as ações em base64 (acoes_b64): caminhos como C:\Windows\... ou textos com
            // cara de SQL/código fazem o mod_security da hospedagem responder 406; "acoes" puro fica para testes
            'acoes_b64'      => ['required_without:acoes', 'nullable', 'string', 'max:40000'],
            'acoes'          => ['required_without:acoes_b64', 'nullable', 'string', 'max:20000'],
        ], [
            'acoes_b64.required_without' => 'Adicione pelo menos uma ação.',
            'acoes.required_without'     => 'Adicione pelo menos uma ação.',
            'nome.required'           => 'Dê um nome à automação.',
            'dispositivo_id.required' => 'Escolha o computador (pareie um primeiro).',
            'dispositivo_id.exists'   => 'Esse computador não está pareado ou foi revogado.',
        ]);

        $cfg = Automacao::limparGatilho($dados['gatilho'], $request->only(['hora', 'dias', 'minutos', 'area']));
        $json = ! empty($dados['acoes_b64']) ? base64_decode($dados['acoes_b64'], true) : ($dados['acoes'] ?? '');
        $acoes = Automacao::limparAcoes(is_string($json) ? json_decode($json, true) : null);

        $a = $automacao ?? new Automacao(['user_id' => $eu]);
        $a->fill([
            'nome'           => trim($dados['nome']),
            'dispositivo_id' => (int) $dados['dispositivo_id'],
            'gatilho'        => $dados['gatilho'],
            'gatilho_config' => $cfg,
            'acoes'          => $acoes,
        ]);
        $a->user_id = $eu;
        if (! $automacao) {
            $a->ativa = true;
        }
        $a->proxima_em = $a->ativa ? $a->proximaApos(now()) : null;
        $a->save();

        return redirect()->route('automacoes.index')->with('ok', $automacao ? 'Automação atualizada.' : 'Automação criada.');
    }

    public function alternar(Request $request, Automacao $automacao): RedirectResponse
    {
        abort_unless($automacao->user_id === $request->user()->id, 404);
        $automacao->ativa = ! $automacao->ativa;
        $automacao->proxima_em = $automacao->ativa ? $automacao->proximaApos(now()) : null;
        $automacao->save();

        return back()->with('ok', $automacao->ativa ? 'Automação ligada.' : 'Automação pausada.');
    }

    public function remover(Request $request, Automacao $automacao): RedirectResponse
    {
        abort_unless($automacao->user_id === $request->user()->id, 404);
        $automacao->delete();

        return back()->with('ok', 'Automação excluída.');
    }

    /** Botão "Rodar agora" (vale para qualquer gatilho). */
    public function rodar(Request $request, Automacao $automacao): RedirectResponse
    {
        abort_unless($automacao->user_id === $request->user()->id, 404);
        $e = $automacao->disparar('Botão no site');

        return back()->with($e ? 'ok' : 'erro', $e
            ? 'Pedido enviado. O computador roda na próxima conferência (até ' . 30 . ' s).'
            : 'Essa automação não tem um computador pareado ativo.');
    }

    // ---------------------------------------------------------------- computadores

    public function parear(Request $request): RedirectResponse
    {
        $eu = $request->user()->id;
        $dados = $request->validate(['computador' => ['required', 'string', 'max:60']], ['computador.required' => 'Dê um nome ao computador (ex.: Notebook do trabalho).']);

        if (AutomacaoDispositivo::where('user_id', $eu)->whereNull('revogado_em')->count() >= self::MAX_DISPOSITIVOS) {
            return back()->withErrors(['computador' => 'Limite de ' . self::MAX_DISPOSITIVOS . ' computadores. Revogue um antes.']);
        }

        [$d, $token] = AutomacaoDispositivo::parear($eu, trim($dados['computador']));

        // o token aparece só nesta resposta (flash); no banco fica apenas o hash
        return redirect()->route('automacoes.index')->with('token_novo', ['nome' => $d->nome, 'token' => $token]);
    }

    public function revogar(Request $request, AutomacaoDispositivo $dispositivo): RedirectResponse
    {
        abort_unless($dispositivo->user_id === $request->user()->id, 404);
        if ($dispositivo->ativo()) {
            $dispositivo->forceFill(['revogado_em' => now()])->save();
            // o que estava esperando esse computador não roda mais
            AutomacaoExecucao::where('dispositivo_id', $dispositivo->id)->whereIn('status', ['pendente', 'enviada'])
                ->update(['status' => 'expirada', 'resultado' => 'Computador revogado.', 'terminada_em' => now()]);
        }

        return back()->with('ok', 'Computador revogado: o token antigo não funciona mais.');
    }

    public function limparHistorico(Request $request): RedirectResponse
    {
        AutomacaoExecucao::where('user_id', $request->user()->id)->whereNotIn('status', ['pendente', 'enviada'])->delete();

        return back()->with('ok', 'Histórico limpo.');
    }

    /** Baixa o agente (pasta agente/ do projeto) em .zip, com o endereço do site já preenchido. */
    public function baixarAgente(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        $zip = \App\Support\PacoteAgente::gerar();
        if ($zip === null) {
            return back()->with('erro', 'Os arquivos do agente não estão no servidor (pasta agente/ junto do código). Avise o suporte.');
        }

        return response($zip, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="work-flower-agente.zip"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /** Lista de áreas para o filtro dos gatilhos de tarefa (usada na view). */
    public static function areas(): array
    {
        return array_map(fn ($a) => $a['nome'], Tarefa::AREAS);
    }
}
