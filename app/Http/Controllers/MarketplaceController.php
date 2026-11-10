<?php

namespace App\Http\Controllers;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use App\Models\MktPedido;
use App\Models\MktProduto;
use App\Models\User;
use App\Support\Compartilhar;
use App\Support\Integracoes\Planilha;
use App\Support\Marketplaces\Demo;
use App\Support\Marketplaces\MarketplaceErro;
use App\Support\Marketplaces\Marketplaces;
use App\Support\Planos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Carteira › Marketplaces (Mercado Livre, Shopee, Amazon): contas, produtos (custos), anúncios, pedidos e margem.
 * Os dados são do dono da Carteira (vale o workflow compartilhado). O limite de contas vem do plano do dono.
 */
class MarketplaceController extends Controller
{
    private const AREA = 'negocios'; // os marketplaces moram na área Negócios (compartilhamento próprio)
    private const MAX_DEMO = 3;

    private function dono(Request $request): int
    {
        return Compartilhar::dono($request, self::AREA);
    }

    /** Endereço da página, mantendo o workflow aberto (?de=) quando é de outra pessoa. */
    private function pagina(int $donoId, int $usuarioId): string
    {
        return route('carteira.mkt', $donoId !== $usuarioId ? ['de' => $donoId] : []);
    }

    /** true se o dono ainda pode conectar mais uma conta real (a demonstração não conta no limite do plano). */
    private function cabeMaisUma(int $donoId): bool
    {
        $dono = User::findOrFail($donoId);
        $reais = MktConta::where(['user_id' => $donoId, 'demo' => false, 'manual' => false])->count();

        return ! Planos::excede(Planos::slugDe($dono), 'marketplaces', $reais + 1);
    }

    public function show(Request $request)
    {
        $this->dono($request);

        return view('areas.carteira.marketplaces');
    }

    /** GET /carteira/marketplaces/dados: tudo o que a tela mostra. */
    public function dados(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $dono = User::find($donoId);

        $contas = MktConta::where('user_id', $donoId)->orderBy('canal')->orderBy('nome')->get();
        $regras = $contas->mapWithKeys(fn ($c) => [$c->id => Marketplaces::regras($c)]);
        $produtos = MktProduto::where('user_id', $donoId)->orderBy('sku')->get();
        $porSku = $produtos->keyBy('sku');

        $anuncios = MktAnuncio::where('user_id', $donoId)->orderBy('titulo')->get()->map(function ($a) use ($porSku, $regras) {
            $m = Marketplaces::margem($a, $a->sku ? $porSku->get($a->sku) : null, $regras[$a->conta_id] ?? []);

            return [
                'id' => $a->id, 'conta_id' => $a->conta_id, 'externo_id' => $a->externo_id, 'sku' => $a->sku,
                'titulo' => $a->titulo, 'preco' => $a->preco, 'estoque' => $a->estoque, 'status' => $a->status,
                'tipo' => $a->tipo, 'link' => $a->link, 'imagem' => $a->imagem,
                'comissao_pct' => $a->comissao_pct, 'frete_proprio' => $a->frete,
            ] + $m;
        });

        $pedidos = MktPedido::where('user_id', $donoId)->where('data', '>=', now()->subDays((int) config('marketplaces.dias_pedidos', 30)))
            ->orderByDesc('data')->limit(500)->get()->map(fn ($p) => [
                'id' => $p->id, 'conta_id' => $p->conta_id, 'externo_id' => $p->externo_id,
                'data' => $p->data?->toIso8601String(), 'status' => $p->status, 'total' => $p->total,
                'tarifas' => $p->tarifas, 'itens' => $p->itens ?? [],
            ]);

        // anúncios por conta e por SKU contados uma vez só (antes: uma volta na lista inteira para cada conta e produto)
        $porConta = $anuncios->countBy('conta_id');
        $porSkuN = $anuncios->countBy('sku');
        $limite = $dono ? Planos::limite(Planos::slugDe($dono), 'marketplaces') : 1;

        return response()->json([
            'canais' => collect(Marketplaces::canais())->map(fn ($c) => [
                'canal' => $c, 'nome' => Marketplaces::nomeCanal($c),
                'configurado' => Marketplaces::conector($c)->configurado(),
                'regras' => config("marketplaces.canais.{$c}.regras"),
            ])->values(),
            'contas' => $contas->map(fn ($c) => [
                'id' => $c->id, 'canal' => $c->canal, 'canal_nome' => Marketplaces::nomeCanal($c->canal),
                'nome' => $c->nome, 'externo_id' => $c->externo_id, 'demo' => $c->demo, 'manual' => (bool) $c->manual, 'ativa' => $c->ativa,
                'erro' => $c->erro, 'sincronizado_em' => $c->sincronizado_em?->toIso8601String(),
                'regras' => (object) ($c->regras ?? []), 'regras_efetivas' => $regras[$c->id],
                'anuncios' => $porConta[$c->id] ?? 0,
            ])->values(),
            'produtos' => $produtos->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'nome' => $p->nome, 'custo' => $p->custo, 'custo_extra' => $p->custo_extra,
                'anuncios' => $porSkuN[$p->sku] ?? 0,
            ])->values(),
            'anuncios' => $anuncios->values(),
            'pedidos' => $pedidos->values(),
            'dias_pedidos' => (int) config('marketplaces.dias_pedidos', 30),
            'limite' => $limite,
            'contas_reais' => $contas->where('demo', false)->where('manual', false)->count(),
            'plano' => $dono ? Planos::nome(Planos::slugDe($dono)) : '',
        ]);
    }

    /* ================= contas ================= */

    /** GET /carteira/marketplaces/conectar/{canal}: manda o vendedor autorizar no marketplace. */
    public function conectar(Request $request, string $canal): RedirectResponse
    {
        $donoId = $this->dono($request);
        $voltar = $this->pagina($donoId, $request->user()->id);
        $conector = Marketplaces::conector($canal);

        if (! $conector->configurado()) {
            return redirect($voltar)->with('mkt_erro', 'A conexão automática com ' . Marketplaces::nomeCanal($canal) . ' ainda não foi ativada neste site. Use “Importar planilha” na aba ' . Marketplaces::nomeCanal($canal) . ' (relatório exportado da sua loja).');
        }
        if (! $this->cabeMaisUma($donoId)) {
            $plano = Planos::nome(Planos::slugDe(User::find($donoId)));

            return redirect($voltar)->with('mkt_erro', "O plano {$plano} já está no limite de contas de marketplace. Desconecte uma conta ou assine um plano maior.");
        }

        $state = Str::random(40);
        $request->session()->put("mkt_state.{$state}", ['dono' => $donoId, 'canal' => $canal, 'em' => now()->timestamp]);

        return redirect()->away($conector->urlAutorizacao($state));
    }

    /** GET /carteira/marketplaces/retorno/{canal}: o marketplace devolve o vendedor com o código de autorização. */
    public function retorno(Request $request, string $canal): RedirectResponse
    {
        $usuario = $request->user();
        $state = (string) $request->query('state');
        $pedido = $state !== '' ? $request->session()->pull("mkt_state.{$state}") : null;

        if (! $pedido || $pedido['canal'] !== $canal || now()->timestamp - $pedido['em'] > 1800) {
            return redirect()->route('carteira.mkt')->with('mkt_erro', 'A autorização expirou ou não veio deste navegador. Tente conectar de novo.');
        }
        $donoId = (int) $pedido['dono'];
        $voltar = $this->pagina($donoId, $usuario->id);
        if (! Compartilhar::podeAcessar($usuario->id, $donoId, self::AREA)) {
            return redirect()->route('carteira.mkt')->with('mkt_erro', 'Você não tem mais acesso a essa Carteira.');
        }
        if ($request->filled('error')) {
            return redirect($voltar)->with('mkt_erro', 'A autorização foi cancelada no ' . Marketplaces::nomeCanal($canal) . '.');
        }

        try {
            $dados = Marketplaces::conector($canal)->conectar($request);
        } catch (MarketplaceErro $e) {
            return redirect($voltar)->with('mkt_erro', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect($voltar)->with('mkt_erro', 'Não consegui concluir a conexão com ' . Marketplaces::nomeCanal($canal) . '. Tente de novo.');
        }

        // a mesma loja conectada de novo só atualiza os tokens
        $conta = MktConta::where(['user_id' => $donoId, 'canal' => $canal, 'externo_id' => $dados['externo_id'], 'demo' => false])->first();
        if (! $conta) {
            if (! $this->cabeMaisUma($donoId)) {
                return redirect($voltar)->with('mkt_erro', 'O plano chegou ao limite de contas de marketplace.');
            }
            $conta = new MktConta(['user_id' => $donoId, 'canal' => $canal, 'demo' => false]);
        }
        $conta->fill($dados + ['ativa' => true, 'erro' => null])->save();

        $r = Marketplaces::sincronizar($conta);

        return redirect($voltar)->with($r['ok'] ? 'mkt_ok' : 'mkt_erro', $r['ok']
            ? "Conta {$conta->nome} conectada: {$r['anuncios']} anúncios e {$r['pedidos']} pedidos trazidos."
            : "Conta {$conta->nome} conectada, mas a primeira sincronização falhou: {$r['erro']}");
    }

    /** POST /carteira/marketplaces/contas/demo { canal }: conta fictícia para conhecer a tela. */
    public function demo(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $canal = $request->validate(['canal' => ['required', Rule::in(Marketplaces::canais())]])['canal'];

        if (MktConta::where(['user_id' => $donoId, 'demo' => true])->count() >= self::MAX_DEMO) {
            return response()->json(['message' => 'Já há ' . self::MAX_DEMO . ' contas de demonstração. Remova uma para criar outra.'], 422);
        }

        $n = MktConta::where(['user_id' => $donoId, 'canal' => $canal, 'demo' => true])->count() + 1;
        $conta = MktConta::create([
            'user_id' => $donoId, 'canal' => $canal, 'demo' => true, 'ativa' => true,
            'nome' => 'Demonstração ' . Marketplaces::nomeCanal($canal) . ($n > 1 ? " {$n}" : ''),
            'externo_id' => 'demo-' . Str::lower(Str::random(6)),
        ]);
        // custos de exemplo para a margem aparecer (não mexe em SKUs que você já cadastrou)
        foreach (Demo::PRODUTOS as $sku => [$nome, $custo, $extra]) {
            MktProduto::firstOrCreate(['user_id' => $donoId, 'sku' => $sku], ['nome' => $nome, 'custo' => $custo, 'custo_extra' => $extra]);
        }
        $r = Marketplaces::sincronizar($conta);

        return response()->json(['ok' => true, 'message' => "Conta de demonstração criada com {$r['anuncios']} anúncios."]);
    }

    /** Colunas reconhecidas nas planilhas exportadas (Shopee, Amazon, Mercado Livre ou feitas à mão). */
    private const COLUNAS = [
        'pedido'  => ['pedido', 'id do pedido', 'n do pedido', 'numero do pedido', 'order id', 'order sn', 'order number', 'amazon order id', 'venda', 'n de venda', 'id da venda'],
        'data'    => ['data', 'data do pedido', 'data de criacao do pedido', 'data da venda', 'date', 'order date', 'order creation date', 'purchase date', 'created'],
        'status'  => ['status', 'status do pedido', 'situacao', 'estado', 'order status', 'item status'],
        'id'      => ['id', 'id do anuncio', 'id do produto', 'item id', 'product id', 'listing id', 'asin', 'asin1', 'mlb', 'codigo do anuncio'],
        'sku'     => ['sku', 'seller sku', 'sku do vendedor', 'sku de referencia', 'numero de referencia sku', 'n de referencia do sku principal', 'sku principal', 'parent sku', 'codigo', 'codigo do produto'],
        'titulo'  => ['titulo', 'nome', 'nome do produto', 'produto', 'anuncio', 'title', 'item name', 'product name', 'descricao'],
        'preco'   => ['preco', 'preco de venda', 'preco unitario', 'preco acordado', 'preco original', 'price', 'item price', 'your price', 'valor unitario'],
        'qtd'     => ['quantidade', 'qtd', 'unidades', 'quantity', 'quantity purchased', 'qty'],
        'estoque' => ['estoque', 'stock', 'quantidade em estoque', 'estoque disponivel', 'available', 'quantity available'],
        'total'   => ['total', 'valor total', 'total do pedido', 'total global', 'receita', 'order total', 'faturamento', 'valor'],
        'tarifa'  => ['tarifas', 'tarifa', 'tarifa de venda', 'taxa', 'taxas', 'taxa de comissao', 'comissao', 'fee', 'fees', 'commission'],
        'tarifa2' => ['taxa de servico', 'service fee', 'taxa de transacao', 'transaction fee'],
        'custo'   => ['custo', 'custo unitario', 'cost', 'unit cost'],
        'link'    => ['link', 'url', 'link do anuncio', 'permalink'],
    ];

    /**
     * POST /carteira/marketplaces/importar { canal, texto, tipo?: auto|anuncios|pedidos }: planilha de anúncios ou de
     * pedidos exportada da loja (CSV ou colada). Vai para a conta "planilha" do canal, que não conta no limite do plano.
     */
    public function importar(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $d = $request->validate([
            'canal' => ['required', Rule::in(Marketplaces::canais())],
            'texto' => ['required', 'string', 'max:' . (int) config('integracoes.max_bytes', 2097152)],
            'tipo'  => ['nullable', Rule::in(['auto', 'anuncios', 'pedidos'])],
        ]);
        $linhas = Planilha::linhas($d['texto'], self::COLUNAS);
        if (! $linhas) {
            return response()->json(['message' => 'Não reconheci as colunas. A primeira linha precisa ter os nomes (ex.: SKU; Título; Preço; Estoque, ou Pedido; Data; SKU; Quantidade; Preço; Tarifas).'], 422);
        }
        $tipo = $d['tipo'] ?? 'auto';
        if ($tipo === 'auto') {
            $tipo = array_key_exists('pedido', $linhas[0]) ? 'pedidos' : 'anuncios';
        }
        if ($tipo === 'pedidos' && ! array_key_exists('pedido', $linhas[0])) {
            return response()->json(['message' => 'A planilha de pedidos precisa de uma coluna com o número do pedido (ex.: "Pedido" ou "ID do pedido").'], 422);
        }

        $canal = $d['canal'];
        $conta = MktConta::firstOrCreate(
            ['user_id' => $donoId, 'canal' => $canal, 'manual' => true, 'demo' => false],
            ['nome' => Marketplaces::nomeCanal($canal) . ' (planilha)', 'externo_id' => 'planilha-' . $canal, 'ativa' => true],
        );

        $custos = [];
        $n = 0;
        if ($tipo === 'anuncios') {
            foreach ($linhas as $l) {
                $sku = mb_substr(trim((string) ($l['sku'] ?? '')), 0, 80);
                $titulo = mb_substr(trim((string) ($l['titulo'] ?? '')), 0, 255);
                $ext = mb_substr(trim((string) (($l['id'] ?? '') ?: $sku ?: ($titulo !== '' ? 'imp-' . substr(md5(Str::lower($titulo)), 0, 12) : ''))), 0, 80);
                if ($ext === '') continue;
                $campos = array_filter([
                    'sku'     => $sku ?: $ext,
                    'titulo'  => $titulo ?: null,
                    'preco'   => Planilha::numero($l['preco'] ?? null),
                    'estoque' => ($e = Planilha::inteiro($l['estoque'] ?? ($l['qtd'] ?? null))) === null ? null : max(0, $e),
                    'status'  => self::statusAnuncio($l['status'] ?? ''),
                    'link'    => preg_match('#^https?://\S+$#i', $l['link'] ?? '') ? mb_substr($l['link'], 0, 500) : null,
                ], fn ($v) => $v !== null);
                MktAnuncio::updateOrCreate(['conta_id' => $conta->id, 'externo_id' => $ext], $campos + ['user_id' => $donoId, 'sincronizado_em' => now()]);
                if (($c = Planilha::numero($l['custo'] ?? null)) !== null) $custos[$campos['sku']] = [$titulo, $c];
                $n++;
            }
        } else {
            $grupos = [];
            $eua = Planilha::formatoEua(array_column($linhas, 'data'));
            foreach ($linhas as $l) {
                $id = mb_substr(trim((string) ($l['pedido'] ?? '')), 0, 80);
                if ($id !== '') $grupos[$id][] = $l;
            }
            foreach ($grupos as $id => $ls) {
                $itens = [];
                foreach ($ls as $l) {
                    $qtd = max(1, (int) (Planilha::inteiro($l['qtd'] ?? null) ?? 1));
                    $sku = mb_substr(trim((string) ($l['sku'] ?? '')), 0, 80);
                    $preco = Planilha::numero($l['preco'] ?? null);
                    if ($preco === null && ($t = Planilha::numero($l['total'] ?? null)) !== null && count($ls) === 1) $preco = round($t / $qtd, 2);
                    $itens[] = ['externo_id' => (string) ($l['id'] ?? ''), 'sku' => $sku ?: null, 'titulo' => mb_substr((string) ($l['titulo'] ?? ''), 0, 200) ?: null, 'qtd' => $qtd, 'preco' => (float) ($preco ?? 0)];
                    if ($sku !== '' && ($c = Planilha::numero($l['custo'] ?? null)) !== null) $custos[$sku] = [$l['titulo'] ?? '', $c];
                }
                $total = self::porPedido($ls, 'total') ?? round(array_sum(array_map(fn ($i) => $i['preco'] * $i['qtd'], $itens)), 2);
                $t1 = self::porPedido($ls, 'tarifa');
                $t2 = self::porPedido($ls, 'tarifa2');
                MktPedido::updateOrCreate(['conta_id' => $conta->id, 'externo_id' => $id], [
                    'user_id' => $donoId,
                    'data'    => Planilha::data($ls[0]['data'] ?? '', $eua) ?? now(),
                    'status'  => mb_substr(trim((string) ($ls[0]['status'] ?? '')), 0, 40) ?: 'pago',
                    'total'   => $total,
                    'tarifas' => $t1 === null && $t2 === null ? null : round(abs((float) $t1) + abs((float) $t2), 2),
                    'itens'   => $itens,
                ]);
                $n++;
            }
        }
        // custo informado na planilha: entra no catálogo (só o que veio preenchido)
        foreach ($custos as $sku => [$nome, $custo]) {
            MktProduto::updateOrCreate(['user_id' => $donoId, 'sku' => $sku], array_filter(['nome' => mb_substr((string) $nome, 0, 200) ?: null, 'custo' => $custo], fn ($v) => $v !== null));
        }
        $conta->update(['sincronizado_em' => now(), 'erro' => null]);

        return response()->json(['ok' => true, 'tipo' => $tipo, 'importados' => $n, 'conta_id' => $conta->id,
            'message' => $n . ($tipo === 'anuncios' ? ($n === 1 ? ' anúncio importado' : ' anúncios importados') : ($n === 1 ? ' pedido importado' : ' pedidos importados'))
                . ' em ' . $conta->nome . '.' . ($custos ? ' Custos de ' . count($custos) . ' SKUs atualizados.' : '')]);
    }

    private static function statusAnuncio(string $s): ?string
    {
        $s = Planilha::normalizar($s);
        if ($s === '') return null;
        if (preg_match('/paus|inativ|inactive|unlist|suspens|desativ|paused|incomplete/', $s)) return 'pausado';
        if (preg_match('/encerr|exclu|delet|closed|finaliz|banned|removid/', $s)) return 'encerrado';

        return 'ativo';
    }

    /** Valor por pedido quando a planilha tem uma linha por item: o mesmo número repetido vale uma vez; diferentes, somam. */
    private static function porPedido(array $linhas, string $campo): ?float
    {
        $v = array_values(array_filter(array_map(fn ($l) => Planilha::numero($l[$campo] ?? null), $linhas), fn ($x) => $x !== null));
        if (! $v) return null;

        return count(array_unique($v)) === 1 ? $v[0] : round(array_sum($v), 2);
    }

    private function contasSelecionadas(Request $request, int $donoId, int $max = 200)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', "max:{$max}"], 'ids.*' => ['integer']])['ids'];

        return MktConta::where('user_id', $donoId)->whereIn('id', $ids)->get();
    }

    /** POST /carteira/marketplaces/sincronizar { ids }: sincroniza as contas escolhidas (até 10 por vez). */
    public function sincronizar(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $saida = [];
        foreach ($this->contasSelecionadas($request, $donoId, 10) as $conta) {
            $saida[] = ['id' => $conta->id, 'nome' => $conta->nome] + ($conta->ativa
                ? Marketplaces::sincronizar($conta)
                : ['ok' => false, 'anuncios' => 0, 'pedidos' => 0, 'erro' => 'conta pausada']);
        }

        return response()->json(['contas' => $saida]);
    }

    /** PATCH /carteira/marketplaces/contas { ids, nome?, ativa?, regras? }: edita uma ou várias contas de uma vez. */
    public function editarContas(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'min:1', 'max:120'],
            'ativa' => ['sometimes', 'boolean'],
            'regras' => ['sometimes', 'array'],
            'regras.comissao_pct' => ['nullable', 'numeric', 'between:0,99'],
            'regras.taxa_fixa' => ['nullable', 'numeric', 'between:0,100000'],
            'regras.taxa_fixa_abaixo_de' => ['nullable', 'numeric', 'between:0,1000000'],
            'regras.frete' => ['nullable', 'numeric', 'between:0,100000'],
            'regras.imposto_pct' => ['nullable', 'numeric', 'between:0,99'],
        ]);
        $contas = $this->contasSelecionadas($request, $donoId);
        if (isset($dados['nome']) && $contas->count() !== 1) {
            return response()->json(['message' => 'Renomeie uma conta por vez.'], 422);
        }

        foreach ($contas as $c) {
            if (array_key_exists('regras', $dados)) {
                // só os campos enviados mudam; vazio volta para o valor de referência do canal
                $regras = array_merge($c->regras ?? [], $dados['regras']);
                $c->regras = array_filter($regras, fn ($v) => $v !== null && $v !== '');
            }
            if (isset($dados['nome'])) {
                $c->nome = $dados['nome'];
            }
            if (isset($dados['ativa'])) {
                $c->ativa = $dados['ativa'];
            }
            $c->save();
        }

        return response()->json(['ok' => true, 'contas' => $contas->count()]);
    }

    /** DELETE /carteira/marketplaces/contas { ids }: desconecta (apaga a conta, os anúncios e os pedidos dela aqui). */
    public function removerContas(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $n = 0;
        foreach ($this->contasSelecionadas($request, $donoId) as $c) {
            $c->delete();
            $n++;
        }

        return response()->json(['ok' => true, 'removidas' => $n]);
    }

    /* ================= produtos ================= */

    /** PUT /carteira/marketplaces/produtos { produtos: [{ sku, nome?, custo, custo_extra? }] }: cria ou atualiza por SKU. */
    public function salvarProdutos(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $lista = $request->validate([
            'produtos' => ['required', 'array', 'min:1', 'max:2000'],
            'produtos.*.sku' => ['required', 'string', 'max:80'],
            'produtos.*.nome' => ['nullable', 'string', 'max:200'],
            'produtos.*.custo' => ['nullable', 'numeric', 'between:0,10000000'],
            'produtos.*.custo_extra' => ['nullable', 'numeric', 'between:0,10000000'],
        ])['produtos'];

        $n = 0;
        foreach ($lista as $p) {
            $sku = trim($p['sku']);
            if ($sku === '') {
                continue;
            }
            $campos = array_filter([
                'nome' => array_key_exists('nome', $p) ? $p['nome'] : null,
                'custo' => $p['custo'] ?? null,
                'custo_extra' => $p['custo_extra'] ?? null,
            ], fn ($v) => $v !== null);
            MktProduto::updateOrCreate(['user_id' => $donoId, 'sku' => $sku], $campos);
            $n++;
        }

        return response()->json(['ok' => true, 'salvos' => $n]);
    }

    /** DELETE /carteira/marketplaces/produtos { ids } */
    public function removerProdutos(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['integer']])['ids'];

        return response()->json(['ok' => true, 'removidos' => MktProduto::where('user_id', $donoId)->whereIn('id', $ids)->delete()]);
    }

    /* ================= anúncios ================= */

    private function anunciosSelecionados(Request $request, int $donoId, int $max)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', "max:{$max}"], 'ids.*' => ['integer']])['ids'];

        return MktAnuncio::where('user_id', $donoId)->whereIn('id', $ids)->with('conta')->get();
    }

    /** POST /carteira/marketplaces/anuncios/acao { ids, acao, valor }: envia a mudança para cada marketplace (até 50 por vez). */
    public function acaoAnuncios(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $dados = $request->validate([
            'acao' => ['required', Rule::in(['preco', 'preco_pct', 'margem', 'estoque', 'estoque_soma', 'status'])],
            'valor' => ['required'],
        ]);
        if ($dados['acao'] === 'status') {
            $request->validate(['valor' => [Rule::in(['ativo', 'pausado'])]]);
        } else {
            $request->validate(['valor' => ['numeric', 'between:-1000000,10000000']]);
        }

        return response()->json(Marketplaces::aplicar($this->anunciosSelecionados($request, $donoId, 50), $dados['acao'], $dados['valor']));
    }

    /** PATCH /carteira/marketplaces/anuncios { ids, comissao_pct?, frete? }: ajustes só da conta da margem (não vão ao marketplace). */
    public function editarAnuncios(Request $request): JsonResponse
    {
        $donoId = $this->dono($request);
        $dados = $request->validate([
            'comissao_pct' => ['sometimes', 'nullable', 'numeric', 'between:0,99'],
            'frete' => ['sometimes', 'nullable', 'numeric', 'between:0,100000'],
        ]);
        $campos = collect($dados)->only(['comissao_pct', 'frete'])->all();
        if (! $campos) {
            return response()->json(['message' => 'Nada para mudar.'], 422);
        }
        $n = 0;
        foreach ($this->anunciosSelecionados($request, $donoId, 2000) as $a) {
            $a->update($campos);
            $n++;
        }

        return response()->json(['ok' => true, 'anuncios' => $n]);
    }
}
