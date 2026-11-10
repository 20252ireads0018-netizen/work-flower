<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use App\Support\Compartilhar;
use App\Support\QuestoesBanco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Apoio dos cartões do quadro (public/js/cartoes-extra.js e graficos.js):
 * - banco de questões abertas (filtros por área, banca, matéria, assunto, ano, dificuldade e "com comentário";
 *   busca paginada e simulado sorteado);
 * - tarefas da área para ligar a um hábito (Hábitos) e criar o checklist de integração (RH).
 */
class CartoesController extends Controller
{
    public const POR_PAGINA = 20;
    public const MAX_SIMULADO = 100;

    /* ---------- banco de questões ---------- */

    public function bancoFiltros(): JsonResponse
    {
        $total = QuestoesBanco::garantir();

        return response()->json(['total' => $total] + QuestoesBanco::filtros());
    }

    public function banco(Request $request): JsonResponse
    {
        $f = $this->filtrosPedido($request);
        QuestoesBanco::garantir();
        $consulta = $this->consulta($f);
        $total = (clone $consulta)->count();
        $pagina = max(1, (int) $request->query('pagina', 1));
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min($pagina, $paginas);
        $itens = $consulta->orderByDesc('ano')->orderBy('prova')->orderBy('id')
            ->forPage($pagina, self::POR_PAGINA)->get()->map(fn ($q) => QuestoesBanco::paraJs($q));

        return response()->json(['total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por' => self::POR_PAGINA, 'itens' => $itens]);
    }

    /** N questões sorteadas dentro dos filtros. */
    public function simulado(Request $request): JsonResponse
    {
        $f = $this->filtrosPedido($request);
        $n = (int) $request->validate(['n' => ['required', 'integer', 'min:1', 'max:' . self::MAX_SIMULADO]])['n'];
        QuestoesBanco::garantir();
        $ids = $this->consulta($f)->pluck('id')->all();
        shuffle($ids);
        $ids = array_slice($ids, 0, $n);
        $itens = $ids ? DB::table('questoes_banco')->whereIn('id', $ids)->get()->map(fn ($q) => QuestoesBanco::paraJs($q))->shuffle()->values() : collect();

        return response()->json(['total' => count($ids), 'itens' => $itens]);
    }

    private function filtrosPedido(Request $request): array
    {
        return $request->validate([
            'area'        => ['nullable', 'string', 'max:40'],
            'banca'       => ['nullable', 'string', 'max:40'],
            'materia'     => ['nullable', 'string', 'max:60'],
            'assunto'     => ['nullable', 'string', 'max:60'],
            'ano'         => ['nullable', 'integer', 'between:1990,2100'],
            'dificuldade' => ['nullable', Rule::in(array_keys(QuestoesBanco::DIFICULDADES))],
            'busca'       => ['nullable', 'string', 'max:80'],
            'comentadas'  => ['nullable', 'boolean'],
            'pagina'      => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function consulta(array $f)
    {
        $q = DB::table('questoes_banco');
        foreach (['area', 'banca', 'materia', 'assunto', 'ano', 'dificuldade'] as $c) {
            if (isset($f[$c]) && $f[$c] !== '') {
                $q->where($c, $f[$c]);
            }
        }
        if (! empty($f['comentadas'])) {
            $q->whereNotNull('comentario');
        }
        if (! empty($f['busca'])) {
            $termo = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($f['busca'])) . '%';
            $q->where(fn ($w) => $w->where('enunciado', 'like', $termo)->orWhere('prova', 'like', $termo));
        }

        return $q;
    }

    /* ---------- tarefas da área ---------- */

    /** Tarefas da área (para ligar um hábito a uma tarefa): as concluídas trazem o dia da conclusão. */
    public function tarefas(Request $request, string $area): JsonResponse
    {
        $dono = Compartilhar::dono($request, $area);
        $lista = Tarefa::where('user_id', $dono)->where('area', $area)->orderByDesc('updated_at')->limit(300)->get()
            ->map(fn (Tarefa $t) => [
                'id' => $t->id, 'titulo' => $t->titulo, 'status' => $t->status,
                'concluida_em' => $t->status === 'concluida' ? $t->updated_at?->format('Y-m-d') : null,
                'prazo' => $t->prazo?->format('Y-m-d'),
            ]);

        return response()->json(['tarefas' => $lista]);
    }

    /** Cria várias tarefas de uma vez (checklist de integração do RH). */
    public function criarTarefas(Request $request, string $area): JsonResponse
    {
        $dono = Compartilhar::dono($request, $area);
        abort_unless(Compartilhar::podeEditar($request->user()->id, $dono, $area), 403, 'O dono deste workflow liberou só a visualização para você.');
        $d = $request->validate([
            'tarefas'             => ['required', 'array', 'min:1', 'max:40'],
            'tarefas.*.titulo'    => ['required', 'string', 'max:150'],
            'tarefas.*.descricao' => ['nullable', 'string', 'max:1000'],
            'tarefas.*.prazo'     => ['nullable', 'date_format:Y-m-d'],
            'categoria'           => ['nullable', 'string', 'max:40'],
        ]);
        $criadas = [];
        foreach ($d['tarefas'] as $t) {
            $criadas[] = Tarefa::create([
                'user_id' => $dono, 'area' => $area, 'titulo' => trim($t['titulo']), 'descricao' => $t['descricao'] ?? null,
                'prazo' => $t['prazo'] ?? null, 'categoria' => trim((string) ($d['categoria'] ?? '')) ?: null, 'status' => 'pendente',
            ])->id;
        }
        \App\Support\Equipe::registrar($dono, $area, $request->user()->id, 'criou ' . count($criadas) . ' tarefas de integração', 'Tarefas');

        return response()->json(['ok' => true, 'ids' => $criadas]);
    }
}
