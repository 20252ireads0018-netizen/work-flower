<?php

namespace App\Http\Controllers;

use App\Models\NotionIntegracao;
use App\Support\Notion;
use App\Support\NotionErro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Negócios › Integrações › Notion. Liga a conta ao Notion (login pelo Notion com a integração pública do site, ou
 * token de integração interna colado pela pessoa), lista as páginas e bancos de dados compartilhados com a
 * integração e devolve o conteúdo em Markdown/tabela. Quem cria os cartões é o navegador (public/js/notas.js),
 * no quadro aberto; o botão "Sincronizar com o Notion" pede de novo o conteúdo dos cartões que vieram do Notion.
 * O token nunca volta para o navegador.
 */
class NotionController extends Controller
{
    use Concerns\CorpoCodificado;

    /** Páginas por pedido de importação/sincronização (o navegador manda em lotes). */
    public const LOTE = 10;

    private function minha(Request $request): ?NotionIntegracao
    {
        return NotionIntegracao::where('user_id', $request->user()->id)->first();
    }

    private function resumo(?NotionIntegracao $i): array
    {
        return [
            'conectado'   => (bool) $i,
            'tipo'        => $i?->tipo,
            'workspace'   => $i?->workspace_nome,
            'oauth'       => Notion::oauthConfigurado(),
            'retorno'     => Notion::retorno(),
            'ultimo_erro' => $i?->ultimo_erro,
            'ultimo_sync' => $i?->ultimo_sync_em?->toIso8601String(),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->resumo($this->minha($request)));
    }

    /** Token de integração interna colado pela pessoa (confere no Notion antes de guardar). */
    public function salvarToken(Request $request): JsonResponse
    {
        $this->decodificarCorpo($request);
        $token = trim((string) $request->validate(['token' => ['required', 'string', 'max:200']])['token']);
        if (! Notion::tokenValido($token)) {
            throw ValidationException::withMessages(['token' => 'Cole o "Internal Integration Secret" como o Notion mostra (começa com ntn_ ou secret_).']);
        }
        try {
            $eu = (new Notion($token))->eu();
        } catch (NotionErro $e) {
            throw ValidationException::withMessages(['token' => $e->status === 401 ? 'O Notion não reconheceu este token. Confira e cole de novo.' : $e->getMessage()]);
        }

        $i = $this->minha($request) ?? new NotionIntegracao();
        $i->user_id = $request->user()->id;
        $i->fill(['access_token' => $token, 'tipo' => 'interno', 'bot_id' => $eu['bot_id'], 'workspace_nome' => $eu['workspace_nome'], 'workspace_id' => null]);
        $i->ultimo_erro = null;
        $i->save();

        return response()->json($this->resumo($i));
    }

    /** Vai para a tela de autorização do Notion (integração pública do site). */
    public function conectar(Request $request): RedirectResponse
    {
        abort_unless(Notion::oauthConfigurado(), 404);
        $estado = Str::random(40);
        $request->session()->put('notion_state', ['v' => $estado, 't' => now()->timestamp]);

        return redirect()->away(Notion::urlAutorizacao($estado));
    }

    /** Retorno do Notion: ?code=...&state=... (ou ?error=access_denied). */
    public function retorno(Request $request): RedirectResponse
    {
        $voltar = fn (string $msg, bool $ok = false) => redirect()->route('areas.negocios')->with($ok ? 'notion_ok' : 'notion_erro', $msg);
        $guardado = $request->session()->pull('notion_state');
        $estado = (string) $request->query('state');
        if (! is_array($guardado) || $estado === '' || ! hash_equals((string) ($guardado['v'] ?? ''), $estado)
            || now()->timestamp - (int) ($guardado['t'] ?? 0) > 1800) {
            return $voltar('A autorização expirou ou não veio deste navegador. Tente conectar de novo.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $voltar('A conexão foi cancelada no Notion.');
        }

        try {
            $t = Notion::trocarCodigo((string) $request->query('code'));
        } catch (RuntimeException $e) {
            return $voltar($e->getMessage());
        }

        $i = $this->minha($request) ?? new NotionIntegracao();
        $i->user_id = $request->user()->id;
        $i->fill($t + ['tipo' => 'oauth']);
        $i->ultimo_erro = null;
        $i->save();

        return $voltar('Notion conectado' . ($i->workspace_nome ? ' (' . $i->workspace_nome . ')' : '') . '. Para importar, clique com o botão direito num quadro › Importar do Notion.', true);
    }

    public function remover(Request $request): JsonResponse
    {
        $this->minha($request)?->delete();

        return response()->json($this->resumo(null));
    }

    /** Páginas e bancos de dados compartilhados com a integração. ?q= filtra pelo título. */
    public function paginas(Request $request): JsonResponse
    {
        $i = $this->exigir($request);
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        try {
            $itens = (new Notion($i->access_token))->buscar($q);
        } catch (NotionErro $e) {
            return $this->falhou($i, $e);
        }

        return response()->json(['itens' => $itens]);
    }

    /**
     * Conteúdo de até LOTE páginas/bancos: { itens: [{ id, tipo, titulo, editado, url, texto | cab+linhas }],
     * erros: [{ id, erro }] }. Serve para importar e para sincronizar.
     */
    public function conteudo(Request $request): JsonResponse
    {
        $this->decodificarCorpo($request);
        $d = $request->validate([
            'itens'        => ['required', 'array', 'min:1', 'max:' . self::LOTE],
            'itens.*.id'   => ['required', 'string', 'regex:/^[0-9a-fA-F-]{32,36}$/'],
            'itens.*.tipo' => ['nullable', 'in:pagina,banco'],
        ]);
        $i = $this->exigir($request);
        $api = new Notion($i->access_token);

        @set_time_limit(120);
        $itens = $erros = [];
        foreach ($d['itens'] as $x) {
            try {
                $itens[] = $api->conteudo($x['id'], $x['tipo'] ?? null);
            } catch (NotionErro $e) {
                if ($e->status === 401 || $e->status === 429) return $this->falhou($i, $e);
                $erros[] = ['id' => $x['id'], 'erro' => $e->getMessage()];
            }
        }
        $i->forceFill(['ultimo_sync_em' => now(), 'ultimo_erro' => null])->save();

        return response()->json(['itens' => $itens, 'erros' => $erros]);
    }

    private function exigir(Request $request): NotionIntegracao
    {
        $i = $this->minha($request);
        abort_unless($i, 409, 'Conecte o Notion em Negócios › Integrações primeiro.');

        return $i;
    }

    private function falhou(NotionIntegracao $i, NotionErro $e): JsonResponse
    {
        $i->forceFill(['ultimo_erro' => mb_substr($e->getMessage(), 0, 255)])->save();

        return response()->json(['message' => $e->getMessage()], $e->status === 401 ? 409 : 502);
    }
}
