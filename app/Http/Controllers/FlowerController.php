<?php

namespace App\Http\Controllers;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
use App\Models\NegocioDado;
use App\Models\Tarefa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Meu Flower": um mapa mental gigante com todo o workflow da conta.
 * Pessoa no centro, uma pétala por área e, dentro dela, abas › pastas › cartões › tarefas.
 *
 * Só LÊ o que as áreas já guardam (nada muda no jeito de gravar):
 *   corpo    -> corpo_dados "estado"        mente    -> corpo_dados "mente.estado"
 *   carteira -> carteira_dados "estado"     negocios -> negocio_dados "estado"
 *   diverso  -> diverso_dados "estado"      tarefas  -> tabela tarefas
 * Cada "estado" tem blocos [{id, secao, tipo, titulo, pasta?}]; o Diverso tem também as abas da pessoa.
 * As abas e os cartões fixos das outras áreas vêm das páginas (public/js/{area}.js e resources/views/areas/*).
 */
class FlowerController extends Controller
{
    /** Cor de cada pétala (paleta dos cartões, Tarefa::CORES). */
    public const CORES = [
        'corpo'    => '#f472b6',
        'mente'    => '#a78bfa',
        'carteira' => '#2dd4bf',
        'negocios' => '#f2c230',
        'diverso'  => '#fb923c',
    ];

    /** Abas fixas de cada área, na ordem da página (Diverso: as abas são da pessoa). */
    private const ABAS = [
        'corpo' => [
            'tarefas' => 'Tarefas', 'treinos' => 'Treinos', 'cargas' => 'Cargas', 'dietas' => 'Dietas', 'agua' => 'Água',
            'calorias' => 'Calorias', 'metas' => 'Metas', 'mapa' => 'Mapa mental', 'livre' => 'Quadro livre',
        ],
        'mente' => [
            'tarefas' => 'Tarefas', 'livros' => 'Livros', 'estudos' => 'Estudos', 'notas' => 'Anotações', 'faculdade' => 'Faculdade',
            'mapa' => 'Mapa mental', 'concursos' => 'Concursos', 'livre' => 'Quadro livre',
        ],
        'carteira' => [
            'tarefas' => 'Tarefas', 'carteira' => 'Carteira', 'invest' => 'Investimentos', 'trabalho' => 'Trabalho',
            'mapa' => 'Mapa mental', 'livre' => 'Quadro livre',
        ],
        'negocios' => [
            'negocios' => 'Negócios', 'produtos' => 'Produtos', 'clientes' => 'Clientes', 'docs' => 'Documentos',
            'loja' => 'Loja e marketplaces', 'equipe' => 'Equipe', 'calls' => 'Calls', 'integracoes' => 'Integrações',
            'tarefas' => 'Tarefas', 'livre' => 'Quadro livre',
        ],
    ];

    /** Cartões fixos (os que a página já traz), por aba: bid => título. */
    private const FIXOS = [
        'corpo' => [
            'tarefas'  => ['tarefa-lista' => 'Tarefas'],
            'treinos'  => ['semana' => 'Semana', 'treino-form' => 'Novo treino', 'treino-lista' => 'Meus treinos'],
            'cargas'   => ['carga-nova' => 'Nova carga', 'carga-lista' => 'Exercícios e cargas'],
            'dietas'   => ['dieta-resumo' => 'Resumo do plano', 'dieta-plano' => 'Refeições'],
            'agua'     => ['agua-hoje' => 'Água de hoje', 'agua-meta' => 'Meta e atalhos', 'agua-semana' => 'Últimos 7 dias'],
            'calorias' => ['cal-registrar' => 'Registrar alimento', 'cal-resumo' => 'Resumo de hoje', 'cal-diario' => 'Diário de hoje'],
            'metas'    => ['metas' => 'Metas'],
        ],
        'mente' => [
            'tarefas'   => ['tarefa-lista' => 'Tarefas'],
            'livros'    => ['livro-lista' => 'Biblioteca', 'livro-leitor' => 'Leitor', 'livro-destaques' => 'Destaques e marcadores'],
            'estudos'   => ['est-novo' => 'Estudei hoje', 'est-cursos' => 'Cursos', 'est-revisar' => 'Para revisar', 'est-lista' => 'Conteúdos estudados'],
            'notas'     => ['nota-lista' => 'Anotações', 'nota-editor' => 'Editor'],
            'faculdade' => ['fac-semana' => 'Semana', 'fac-disc-form' => 'Nova disciplina', 'fac-disc' => 'Disciplinas', 'fac-aval' => 'Provas e trabalhos'],
        ],
        'carteira' => [
            'tarefas'  => ['tarefa-lista' => 'Tarefas'],
            'carteira' => ['cart-resumo' => 'Visão geral', 'cart-contas' => 'Contas e bancos', 'cart-novo' => 'Novo lançamento',
                           'cart-cats' => 'Gastos por categoria', 'cart-relatorio' => 'Relatório de gastos'],
            'invest'   => ['inv-resumo' => 'Resultado dos investimentos', 'inv-novo' => 'Novo ativo', 'inv-lista' => 'Meus ativos',
                           'inv-radar' => 'Radar: oportunidades para acompanhar'],
            'trabalho' => ['projetos' => 'Projetos'],
        ],
        'negocios' => [
            'tarefas'  => ['tarefa-lista' => 'Tarefas'],
            'negocios' => ['neg-negocios' => 'Meus negócios', 'neg-resumo' => 'Resumo do mês', 'neg-lanc' => 'Lançar venda ou custo',
                           'neg-caixa' => 'Fluxo de caixa', 'neg-dash' => 'Dashboard', 'neg-conexoes' => 'Marketplaces e tráfego'],
            'clientes' => ['cli-lista' => 'Clientes'],
            'docs'     => ['doc-lista' => 'Documentos', 'doc-editor' => 'Editor', 'doc-previa' => 'Prévia'],
        ],
        'diverso' => [
            'quadro' => ['tarefa-lista' => 'Tarefas'],
        ],
    ];

    /** Nome de cada tipo de cartão (para cartão sem título e para o detalhe do nó). */
    private const TIPOS = [
        'texto' => 'Texto', 'tabela' => 'Tabela', 'lista' => 'Lista', 'imagem' => 'Imagem', 'mapa' => 'Mapa mental',
        'pasta' => 'Pasta', 'documento' => 'Documento', 'codigo' => 'Código', 'banco' => 'Banco de dados',
        'tabletop' => 'Tabletop', 'questoes' => 'Questões', 'criador' => 'Criador de conteúdo', 'rh' => 'RH',
        'editorpdf' => 'Editor de PDF', 'editorimg' => 'Editor de imagem', 'editorvid' => 'Editor de vídeo',
        'grafico' => 'Gráfico', 'habitos' => 'Hábitos', 'musica' => 'Música', 'claude' => 'Claude', 'arquivo' => 'Arquivo',
    ];

    /** Aba onde ficam as tarefas de cada área. */
    private const ABA_TAREFAS = ['corpo' => 'tarefas', 'mente' => 'tarefas', 'carteira' => 'tarefas', 'negocios' => 'tarefas', 'diverso' => 'quadro'];

    private const MAX_CARTOES_POR_AREA = 600;
    private const MAX_TAREFAS_POR_AREA = 400;

    public function show(): View
    {
        return view('flower.index', ['cores' => self::CORES]);
    }

    public function dados(Request $request): JsonResponse
    {
        $user = $request->user();
        $uid  = $user->id;

        $tarefas = Tarefa::where('user_id', $uid)
            ->orderByRaw("case when status = 'concluida' then 1 else 0 end")
            ->orderByRaw('prazo is null')->orderBy('prazo')->orderBy('id')
            ->get(['id', 'area', 'titulo', 'status', 'prazo', 'cor'])
            ->groupBy('area');

        $areas = [];
        foreach (Tarefa::AREAS as $slug => $a) {
            $areas[] = $this->area($slug, $a['nome'], $this->estado($slug, $uid), $tarefas->get($slug, collect()));
        }

        return response()->json([
            'pessoa' => ['nome' => $user->name ?: 'Você'],
            'areas'  => $areas,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** O "estado" JSON de uma área, ou [] se não houver / estiver estragado. */
    private function estado(string $slug, int $uid): array
    {
        [$modelo, $chave] = match ($slug) {
            'corpo'    => [CorpoDado::class, 'estado'],
            'mente'    => [CorpoDado::class, 'mente.estado'],
            'carteira' => [CarteiraDado::class, 'estado'],
            'negocios' => [NegocioDado::class, 'estado'],
            'diverso'  => [DiversoDado::class, 'estado'],
        };
        $valor = $modelo::where('user_id', $uid)->where('chave', $chave)->value('valor');
        $e = is_string($valor) ? json_decode($valor, true) : null;

        return is_array($e) ? $e : [];
    }

    private function area(string $slug, string $nome, array $estado, $tarefas): array
    {
        $url   = url('/areas/' . $slug);
        $no    = fn (string $tipo, string $id) => $slug . ':' . $tipo . ':' . $id;
        $link  = fn (string $bid, string $sec) => $url . '?fav=' . rawurlencode($bid) . '&favsec=' . rawurlencode($sec);

        // abas: fixas da área ou as da pessoa (Diverso)
        $abas = [];
        if ($slug === 'diverso') {
            foreach ((array) ($estado['abas'] ?? []) as $a) {
                if (! is_array($a) || ! is_string($a['id'] ?? null) || $a['id'] === '') continue;
                $abas[$a['id']] = $this->texto($a['nome'] ?? '') ?: 'Aba';
            }
            if (! isset($abas['quadro'])) $abas = ['quadro' => 'Geral'] + $abas;
        } else {
            $abas = self::ABAS[$slug];
        }

        // cartões das áreas (blocos), por aba e por pasta
        $blocos = [];
        foreach ((array) ($estado['blocos'] ?? []) as $b) {
            if (! is_array($b) || ! is_string($b['id'] ?? null) || $b['id'] === '') continue;
            $sec = is_string($b['secao'] ?? null) && $b['secao'] !== '' ? $b['secao'] : array_key_first($abas);
            $blocos[$b['id']] = [
                'id'    => $b['id'],
                'sec'   => $sec,
                'tipo'  => is_string($b['tipo'] ?? null) ? $b['tipo'] : 'texto',
                'nome'  => $this->texto($b['titulo'] ?? ''),
                'pasta' => is_string($b['pasta'] ?? null) ? $b['pasta'] : null,
            ];
            if (count($blocos) >= self::MAX_CARTOES_POR_AREA) break;
            if (! isset($abas[$sec])) $abas[$sec] = ucfirst($sec); // aba que não está mais na lista: o cartão ainda aparece
        }

        $cartao = function (array $b) use ($slug, $no, $link) {
            $tipoNome = self::TIPOS[$b['tipo']] ?? ucfirst($b['tipo']);
            return [
                'id'    => $no($b['tipo'] === 'pasta' ? 'pasta' : 'cartao', $b['id']),
                'tipo'  => $b['tipo'] === 'pasta' ? 'pasta' : 'cartao',
                'nome'  => $b['nome'] ?: $tipoNome,
                'info'  => $tipoNome,
                'link'  => $link($b['id'], $b['sec']),
                'filhos' => [],
            ];
        };

        // pastas: os cartões de dentro viram filhos (pastas dentro de pastas também; ciclo ou pasta sumida -> fica na aba)
        $dentroDe = function (?string $pasta, array $vistos = []) use (&$dentroDe, $blocos, $cartao) {
            $filhos = [];
            foreach ($blocos as $b) {
                if ($b['pasta'] !== $pasta) continue;
                $n = $cartao($b);
                if ($b['tipo'] === 'pasta' && ! in_array($b['id'], $vistos, true)) {
                    $n['filhos'] = $dentroDe($b['id'], [...$vistos, $b['id']]);
                }
                $filhos[] = $n;
            }
            return $filhos;
        };
        $semPasta = array_filter($blocos, fn ($b) => $b['pasta'] === null
            || ! isset($blocos[$b['pasta']]) || $blocos[$b['pasta']]['tipo'] !== 'pasta' || $b['pasta'] === $b['id']);

        // tarefas
        $listaTarefas = [];
        $feitas = 0;
        foreach ($tarefas as $t) {
            if ($t->status === 'concluida') $feitas++;
            if (count($listaTarefas) >= self::MAX_TAREFAS_POR_AREA) continue;
            $listaTarefas[] = [
                'id'     => $no('tarefa', (string) $t->id),
                'tipo'   => 'tarefa',
                'nome'   => $this->texto($t->titulo) ?: 'Tarefa',
                'info'   => (Tarefa::STATUS[$t->status] ?? $t->status) . ($t->prazo ? ' · prazo ' . $t->prazo->format('d/m/Y') : ''),
                'status' => $t->status,
                'cor'    => $t->cor,
                'link'   => $link('tarefa-lista', self::ABA_TAREFAS[$slug]),
                'filhos' => [],
            ];
        }

        $filhosAbas = [];
        foreach ($abas as $sec => $nomeAba) {
            $filhos = [];
            foreach (self::FIXOS[$slug][$sec] ?? [] as $bid => $titulo) {
                $n = [
                    'id' => $no('fixo', $sec . '.' . $bid), 'tipo' => 'cartao', 'nome' => $titulo, 'info' => 'Cartão da área',
                    'link' => $link($bid, $sec), 'filhos' => [],
                ];
                if ($bid === 'tarefa-lista') {
                    $n['filhos'] = $listaTarefas;
                    $n['info']   = count($tarefas) . (count($tarefas) === 1 ? ' tarefa' : ' tarefas') . ' · ' . $feitas . ' concluída' . ($feitas === 1 ? '' : 's');
                }
                $filhos[] = $n;
            }
            foreach ($semPasta as $b) {
                if ($b['sec'] !== $sec) continue;
                $n = $cartao($b);
                if ($b['tipo'] === 'pasta') $n['filhos'] = $dentroDe($b['id'], [$b['id']]);
                $filhos[] = $n;
            }
            // a aba abre direto (o cartão inexistente "_" só troca de aba e não rola para lugar nenhum)
            $filhosAbas[] = [
                'id'     => $no('aba', $sec),
                'tipo'   => 'aba',
                'nome'   => $nomeAba,
                'info'   => 'Aba de ' . self::nomeArea($slug),
                'link'   => $link('_', $sec),
                'filhos' => $filhos,
            ];
        }

        return [
            'id'      => $slug,
            'tipo'    => 'area',
            'nome'    => $nome,
            'cor'     => self::CORES[$slug],
            'link'    => $url,
            'info'    => Tarefa::AREAS[$slug]['descricao'],
            'resumo'  => [
                'abas'     => count($abas),
                'cartoes'  => count(array_filter($blocos, fn ($b) => $b['tipo'] !== 'pasta'))
                            + array_sum(array_map('count', array_intersect_key(self::FIXOS[$slug] ?? [], $abas))),
                'pastas'   => count(array_filter($blocos, fn ($b) => $b['tipo'] === 'pasta')),
                'tarefas'  => count($tarefas),
                'feitas'   => $feitas,
            ],
            'filhos'  => $filhosAbas,
        ];
    }

    private static function nomeArea(string $slug): string
    {
        return Tarefa::AREAS[$slug]['nome'] ?? $slug;
    }

    private function texto($v): string
    {
        return is_string($v) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $v)), 0, 120) : '';
    }
}
