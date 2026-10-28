<?php

namespace App\Http\Controllers;

use App\Models\TabletopImg;
use App\Models\TabletopItem;
use App\Models\TabletopOp;
use App\Models\TabletopSala;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TabletopVivoController extends Controller
{
    private const CFG = ['cols', 'rows', 'cel', 'grade', 'nomes'];
    private const MAX = ['peca' => 300, 'des' => 1500];         // itens por sala
    private const MAX_BYTES = ['peca' => 4000, 'des' => 80000];  // JSON de cada item
    private const MAX_IMGS = 60;
    private const MAX_OPS = 300;          // igual a LOTE no JS
    private const HIST = 500;             // ops guardadas; mais antigas são podadas
    private const POR_RESPOSTA = 200;     // lotes por consulta (o resto vai em `mais`)
    private const PRES_TTL = 5;           // segundos
    private const LIM_IMG = ['mapa' => 1000000, 'peca' => 250000]; // caracteres do data URL (igual ao JS)
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /* ------------------------------------------------------------------ salas */

    /** POST /tabletop-vivo  { titulo, dados } -> { codigo, rev } */
    public function store(Request $r): JsonResponse
    {
        $in = $r->validate([
            'titulo' => 'nullable|string|max:80',
            'dados' => 'required|array',
        ]);

        $sala = DB::transaction(function () use ($in, $r) {
            $dados = $in['dados'];
            $cfg = [];
            foreach (self::CFG as $k) {
                if (array_key_exists($k, $dados) && ($v = $this->cfgValor($k, $dados[$k])) !== null) $cfg[$k] = $v;
            }

            $sala = TabletopSala::create([
                'codigo' => $this->novoCodigo(),
                'user_id' => $r->user()->id,
                'titulo' => $in['titulo'] ?? null,
                'rev' => 0,
                'cfg' => $cfg ?: null,
                'ultima_atividade' => now(),
            ]);

            foreach (['pecas' => 'peca', 'desenho' => 'des'] as $campo => $tipo) {
                $lista = is_array($dados[$campo] ?? null) ? array_slice($dados[$campo], 0, self::MAX[$tipo]) : [];
                $vistos = [];
                foreach ($lista as $item) {
                    $id = is_array($item) && isset($item['id']) && is_scalar($item['id']) ? (string) $item['id'] : '';
                    if (!$this->idOk($id) || isset($vistos[$id])) continue;
                    $limpo = $this->limparItem($tipo, $item, $id);
                    if ($limpo === null) continue;
                    $vistos[$id] = true;
                    TabletopItem::create(['sala_id' => $sala->id, 'tipo' => $tipo, 'item_id' => $id, 'dados' => $limpo]);
                }
            }
            return $sala;
        });

        return response()->json(['codigo' => $sala->codigo, 'rev' => 0]);
    }

    /** GET /tabletop-vivo/{codigo} -> { titulo, dados, rev, imgs, dono } */
    public function show(string $codigo): JsonResponse
    {
        $sala = $this->sala($codigo);
        $sala->forceFill(['ultima_atividade' => now()])->save();

        // `rev` é lido ANTES do estado: se alguém gravar no meio, o cliente reaplica a operação (é idempotente)
        // em vez de perdê-la.
        $rev = (int) $sala->rev;
        return response()->json([
            'titulo' => $sala->titulo,
            'dados' => $this->estado($sala),
            'rev' => $rev,
            'imgs' => $this->chavesImgs($sala),
            'dono' => $this->dono($sala),
            'travada' => (bool) $sala->travada,
        ]);
    }

    /** DELETE /tabletop-vivo/{codigo}  (só o dono) */
    public function destroy(string $codigo): JsonResponse
    {
        $sala = $this->sala($codigo);
        abort_unless($this->dono($sala), 403, 'Só quem abriu a sala pode encerrá-la.');
        $sala->delete();
        return response()->json(['ok' => true]);
    }

    /** PATCH /tabletop-vivo/{codigo}/travar  { travar: bool }  (só o dono) */
    public function travar(Request $r, string $codigo): JsonResponse
    {
        $sala = $this->sala($codigo);
        abort_unless($this->dono($sala), 403, 'Só quem abriu a sala pode travar o mapa.');
        $sala->update(['travada' => $r->boolean('travar')]);
        return response()->json(['travada' => (bool) $sala->travada]);
    }

    /* -------------------------------------------------------------------- ops */

    /** POST /{codigo}/ops  { cid, ops } -> { rev, anterior, ignoradas } */
    public function enviar(Request $r, string $codigo): JsonResponse
    {
        $cid = $this->cid($r->input('cid'));
        $ops = $r->input('ops');
        abort_unless($cid !== null, 422, 'Identificador do cliente inválido.');
        abort_unless(is_array($ops) && count($ops) <= self::MAX_OPS, 422, 'Lote de operações inválido.');

        return $this->comSala($codigo, function (TabletopSala $sala) use ($cid, $ops) {
            $anterior = (int) $sala->rev;
            [$aceitas, $ignoradas] = $this->aplicar($sala, array_values($ops), $this->dono($sala));

            if ($aceitas) {
                $this->avancar($sala, $cid, $aceitas);
            } else {
                $sala->ultima_atividade = now();
                $sala->save();
            }
            return response()->json(['rev' => (int) $sala->rev, 'anterior' => $anterior, 'ignoradas' => $ignoradas]);
        });
    }

    /**
     * GET /{codigo}/ops?desde&cid&n&x&y&px&py
     * -> { rev, ops: [{cid, ops}], reset?, dados?, imgs?, online, mais?, pres, travada }
     */
    public function consultar(Request $r, string $codigo): JsonResponse
    {
        $sala = $this->sala($codigo);
        $desde = (int) $r->query('desde', 0);
        $cid = $this->cid($r->query('cid'));

        [$pres, $online] = $this->presenca($sala, $cid, $r);
        $base = ['online' => $online, 'pres' => $pres, 'travada' => (bool) $sala->travada];
        $rev = (int) $sala->rev;

        $maisAntiga = TabletopOp::where('sala_id', $sala->id)->min('rev');
        $reset = $desde > $rev
            || ($desde < $rev && ($maisAntiga === null || (int) $maisAntiga > $desde + 1));

        if ($reset) {
            return response()->json($base + [
                'reset' => true,
                'rev' => $rev,
                'dados' => $this->estado($sala),
                'imgs' => $this->chavesImgs($sala),
                'ops' => [],
            ]);
        }

        $linhas = $desde < $rev
            ? TabletopOp::where('sala_id', $sala->id)->where('rev', '>', $desde)->orderBy('rev')->limit(self::POR_RESPOSTA + 1)->get()
            : collect();
        $mais = $linhas->count() > self::POR_RESPOSTA;
        $linhas = $linhas->take(self::POR_RESPOSTA);

        $resp = $base + [
            'rev' => $linhas->isNotEmpty() ? (int) $linhas->last()->rev : $rev,
            'ops' => $linhas->map(fn ($l) => ['cid' => $l->cid, 'ops' => $l->ops])->values()->all(),
        ];
        if ($mais) $resp['mais'] = true;
        return response()->json($resp);
    }

    /* ----------------------------------------------------------------- imagens */

    /** GET /{codigo}/img/{chave} -> { v } */
    public function imgVer(string $codigo, string $chave): JsonResponse
    {
        $sala = $this->sala($codigo);
        $img = TabletopImg::where('sala_id', $sala->id)->where('chave', $chave)->first();
        abort_unless($img, 404, 'Imagem não encontrada.');
        return response()->json(['v' => $img->v]);
    }

    /** PUT /{codigo}/img/{chave}  { cid, v } */
    public function imgSalvar(Request $r, string $codigo, string $chave): JsonResponse
    {
        $cid = $this->cid($r->input('cid'));
        $v = $r->input('v');
        $mapa = $chave === 'mapa';
        abort_unless($cid !== null, 422, 'Identificador do cliente inválido.');
        abort_unless($this->imgOk($v, self::LIM_IMG[$mapa ? 'mapa' : 'peca']), 422, 'Imagem inválida ou grande demais.');

        return $this->comSala($codigo, function (TabletopSala $sala) use ($cid, $chave, $mapa, $v) {
            abort_if($mapa && $sala->travada && !$this->dono($sala), 423, 'O mestre travou o mapa.');

            $existe = TabletopImg::where('sala_id', $sala->id)->where('chave', $chave)->exists();
            abort_if(!$existe && TabletopImg::where('sala_id', $sala->id)->count() >= self::MAX_IMGS, 422, 'Limite de imagens da sala atingido.');

            TabletopImg::updateOrCreate(['sala_id' => $sala->id, 'chave' => $chave], ['v' => $v]);
            $rev = $this->avancar($sala, $cid, [['t' => 'img', 'k' => $chave, 'v' => true]]);
            return response()->json(['ok' => true, 'rev' => $rev]);
        });
    }

    /** DELETE /{codigo}/img/{chave}?cid= */
    public function imgRemover(Request $r, string $codigo, string $chave): JsonResponse
    {
        $cid = $this->cid($r->query('cid'));
        abort_unless($cid !== null, 422, 'Identificador do cliente inválido.');

        return $this->comSala($codigo, function (TabletopSala $sala) use ($cid, $chave) {
            abort_if($chave === 'mapa' && $sala->travada && !$this->dono($sala), 423, 'O mestre travou o mapa.');

            $apagadas = TabletopImg::where('sala_id', $sala->id)->where('chave', $chave)->delete();
            $rev = $apagadas ? $this->avancar($sala, $cid, [['t' => 'img', 'k' => $chave, 'v' => false]]) : (int) $sala->rev;
            return response()->json(['ok' => true, 'rev' => $rev]);
        });
    }

    /* ----------------------------------------------------------------- internos */

    private function sala(string $codigo): TabletopSala
    {
        return TabletopSala::where('codigo', strtoupper($codigo))->firstOrFail();
    }

    private function dono(TabletopSala $sala): bool
    {
        return (int) $sala->user_id === (int) auth()->id();
    }

    private function cid($v): ?string
    {
        return is_string($v) && preg_match('/^[A-Za-z0-9]{1,40}$/', $v) ? $v : null;
    }

    private function idOk(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_.\-]{1,40}$/', $id) === 1;
    }

    private function novoCodigo(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $c = '';
            for ($j = 0; $j < 6; $j++) $c .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            if (!TabletopSala::where('codigo', $c)->exists()) return $c;
        }
        abort(500, 'Não consegui gerar um código de sala.');
    }

    /** Bloqueia a linha da sala durante a gravação: a ordem das revisões é única. */
    private function comSala(string $codigo, callable $fn)
    {
        $base = $this->sala($codigo);
        return DB::transaction(function () use ($base, $fn) {
            $sala = TabletopSala::whereKey($base->id)->lockForUpdate()->first();
            abort_unless($sala, 404, 'Sala não encontrada.');
            return $fn($sala);
        });
    }

    /** Sobe a revisão, guarda o lote no histórico e poda o histórico antigo. */
    private function avancar(TabletopSala $sala, string $cid, array $ops): int
    {
        $sala->rev = (int) $sala->rev + 1;
        $sala->ultima_atividade = now();
        $sala->save();

        TabletopOp::create(['sala_id' => $sala->id, 'rev' => $sala->rev, 'cid' => $cid, 'ops' => $ops]);

        if ($sala->rev % 50 === 0) {
            TabletopOp::where('sala_id', $sala->id)->where('rev', '<=', $sala->rev - self::HIST)->delete();
        }
        return (int) $sala->rev;
    }

    /** Aplica o lote. Devolve [operações aceitas já saneadas, quantas foram ignoradas]. */
    private function aplicar(TabletopSala $sala, array $ops, bool $dono): array
    {
        $cfg = $sala->cfg ?? [];
        $aceitas = [];
        $ignoradas = 0;
        $cfgMudou = false;

        $ids = [];
        foreach ($ops as $op) {
            if (is_array($op) && in_array($op['t'] ?? null, ['peca', 'des'], true) && isset($op['id']) && is_scalar($op['id'])) {
                $ids[] = (string) $op['id'];
            }
        }
        $existentes = $ids
            ? TabletopItem::where('sala_id', $sala->id)->whereIn('item_id', array_values(array_unique($ids)))->get()
                ->keyBy(fn ($i) => $i->tipo . ':' . $i->item_id)
            : collect();
        $cont = TabletopItem::where('sala_id', $sala->id)->selectRaw('tipo, count(*) as n')->groupBy('tipo')->pluck('n', 'tipo')->map(fn ($n) => (int) $n)->all();
        $cont += ['peca' => 0, 'des' => 0];

        foreach ($ops as $op) {
            if (!is_array($op) || !array_key_exists('v', $op)) { $ignoradas++; continue; }
            $t = $op['t'] ?? null;

            if ($t === 'cfg') {
                $k = $op['k'] ?? null;
                if (!in_array($k, self::CFG, true) || ($sala->travada && !$dono)) { $ignoradas++; continue; }
                $v = $this->cfgValor($k, $op['v']);
                if ($v === null) { $ignoradas++; continue; }
                $cfg[$k] = $v;
                $cfgMudou = true;
                $aceitas[] = ['t' => 'cfg', 'k' => $k, 'v' => $v];
                continue;
            }

            if ($t === 'peca' || $t === 'des') {
                $id = isset($op['id']) && is_scalar($op['id']) ? (string) $op['id'] : '';
                if (!$this->idOk($id)) { $ignoradas++; continue; }
                $chave = $t . ':' . $id;
                $atual = $existentes->get($chave);

                if ($op['v'] === null) {
                    if ($atual) { $atual->delete(); $existentes->forget($chave); $cont[$t]--; }
                    $aceitas[] = ['t' => $t, 'id' => $id, 'v' => null];
                    continue;
                }

                $limpo = $this->limparItem($t, $op['v'], $id);
                if ($limpo === null) { $ignoradas++; continue; }

                if ($atual) {
                    $atual->update(['dados' => $limpo]);
                } else {
                    if ($cont[$t] >= self::MAX[$t]) { $ignoradas++; continue; }
                    $novo = TabletopItem::create(['sala_id' => $sala->id, 'tipo' => $t, 'item_id' => $id, 'dados' => $limpo]);
                    $existentes->put($chave, $novo);
                    $cont[$t]++;
                }
                $aceitas[] = ['t' => $t, 'id' => $id, 'v' => $limpo];
                continue;
            }

            $ignoradas++; // 'img' não entra por aqui (tem rota própria); qualquer outro tipo é descartado
        }

        if ($cfgMudou) $sala->cfg = $cfg;
        return [$aceitas, $ignoradas];
    }

    private function cfgValor(string $k, $v)
    {
        if (!is_scalar($v)) return null;
        switch ($k) {
            case 'cols':
            case 'rows': return is_numeric($v) ? max(4, min(60, (int) $v)) : null;
            case 'cel':  return is_numeric($v) ? max(28, min(64, (int) $v)) : null;
            default:     return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
    }

    /**
     * Sanea uma peça ou um traço. null = recusado.
     * Os traços são desenhados no cliente com x-html (SVG em texto): por isso só aceitam textos com caracteres
     * seguros (sem < > " ' & = ;). As peças são exibidas com x-text/:style, então só a cor é restrita.
     */
    private function limparItem(string $tipo, $v, string $id): ?array
    {
        if (!is_array($v) || array_is_list($v)) return null;
        $v['id'] = $id;

        if ($tipo === 'peca') {
            if (isset($v['nome'])) $v['nome'] = mb_substr(trim(strip_tags((string) $v['nome'])), 0, 24);
            if (isset($v['cor']) && (!is_string($v['cor']) || !preg_match('/^#[0-9a-fA-F]{3,8}$/', $v['cor']))) return null;
            $ok = $this->valorOk($v, 0, null, 200);
        } else {
            $ok = $this->valorOk($v, 0, '/^[A-Za-z0-9#.,%()\s_\-]*$/', 20000);
        }
        if (!$ok) return null;

        $json = json_encode($v);
        return ($json !== false && strlen($json) <= self::MAX_BYTES[$tipo]) ? $v : null;
    }

    private function valorOk($v, int $prof, ?string $regex, int $maxStr): bool
    {
        if ($prof > 5) return false;
        if (is_string($v)) {
            return strlen($v) <= $maxStr && ($regex === null || preg_match($regex, $v) === 1);
        }
        if (is_array($v)) {
            if (count($v) > 5000) return false;
            foreach ($v as $k => $x) {
                if (is_string($k) && !preg_match('/^[A-Za-z0-9_]{1,30}$/', $k)) return false;
                if (!$this->valorOk($x, $prof + 1, $regex, $maxStr)) return false;
            }
            return true;
        }
        return is_int($v) || is_float($v) || is_bool($v) || $v === null;
    }

    private function imgOk($v, int $lim): bool
    {
        if (!is_string($v) || strlen($v) > $lim) return false;
        if (!preg_match('#^data:image/(?:png|jpeg|webp|gif);base64,#', substr($v, 0, 40), $m)) return false;
        return base64_decode(substr($v, strlen($m[0])), true) !== false; // SVG fica de fora de propósito
    }

    private function estado(TabletopSala $sala): array
    {
        $itens = TabletopItem::where('sala_id', $sala->id)->orderBy('id')->get(['tipo', 'dados']);
        $dados = $sala->cfg ?? [];
        $dados['pecas'] = $itens->where('tipo', 'peca')->map(fn ($i) => $i->dados)->values()->all();
        $dados['desenho'] = $itens->where('tipo', 'des')->map(fn ($i) => $i->dados)->values()->all();
        return $dados;
    }

    private function chavesImgs(TabletopSala $sala): array
    {
        return TabletopImg::where('sala_id', $sala->id)->pluck('chave')->values()->all();
    }

    /**
     * Presença em cache (sem tabela): [cid => {n, x, y, ping, ts}] com TTL de PRES_TTL segundos.
     * Devolve [lista dos OUTROS, quantidade online (inclui quem consulta)].
     */
    private function presenca(TabletopSala $sala, ?string $cid, Request $r): array
    {
        $chave = 'tt:pres:' . $sala->id;
        $agora = microtime(true);

        $atualizar = function () use ($chave, $cid, $r, $agora) {
            $lista = Cache::get($chave, []);
            foreach ($lista as $c => $p) {
                if (($p['ts'] ?? 0) < $agora - self::PRES_TTL) unset($lista[$c]);
            }
            if ($cid !== null) {
                $p = $lista[$cid] ?? ['n' => 'Jogador', 'x' => null, 'y' => null, 'ping' => null];
                $nome = mb_substr(trim(strip_tags((string) $r->query('n', 'Jogador'))), 0, 24);
                $p['n'] = $nome !== '' ? $nome : 'Jogador';
                if (is_numeric($r->query('x')) && is_numeric($r->query('y'))) {
                    $p['x'] = max(0, min(2400, (float) $r->query('x')));   // 60 casas * 40 unidades
                    $p['y'] = max(0, min(2400, (float) $r->query('y')));
                }
                if (is_numeric($r->query('px')) && is_numeric($r->query('py'))) {
                    $p['ping'] = [
                        'x' => max(0, min(2400, (float) $r->query('px'))),
                        'y' => max(0, min(2400, (float) $r->query('py'))),
                        't' => (int) ($agora * 1000),                       // muda a cada ping novo
                    ];
                }
                $p['ts'] = $agora;
                $lista[$cid] = $p;
            }
            Cache::put($chave, $lista, 60);
            return $lista;
        };

        try {
            $lista = Cache::lock('tt:lock:' . $sala->id, 3)->block(1, $atualizar);
        } catch (\Throwable $e) {
            $lista = $atualizar(); // driver sem lock ou lock ocupado: segue sem ele (presença é só visual)
        }

        $outros = [];
        foreach ($lista as $c => $p) {
            if ((string) $c === (string) $cid) continue;
            $outros[] = ['c' => (string) $c, 'n' => $p['n'], 'x' => $p['x'], 'y' => $p['y'], 'ping' => $p['ping']];
        }
        return [$outros, count($lista)];
    }
}

