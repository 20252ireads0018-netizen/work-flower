<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Mover cartões (blocos livres) de uma aba para outra aba de OUTRA área, sem perder nada:
 * o bloco (com os dados), a posição/tamanho/cor/fundo do layout, as imagens (img.<id> e img.<id>.*)
 * e os PDFs do cartão Editor de PDF (storage/app/editorpdf/<dono>/<área>/<arquivo>.pdf).
 *
 * - Só os dados da própria conta (nada de workflow de outra pessoa).
 * - Cartões fixos das áreas (Treinos, Contas, Livros...) não saem do lugar: são parte da página da área.
 *   Continuam guardados lá mesmo com a área escondida.
 * - Tipo de cartão que a área de destino não sabe mostrar (ex.: "Documento" só existe na Mente; "Banco de dados",
 *   "GitHub / VS Code" e "Tabletop" só no Diverso) fica onde está e aparece na resposta como "ficaram".
 * - Diverso e as áreas criadas (AreasConta) são o mesmo estado: as abas de uma área criada têm "grupo".
 *
 * Os estados são lidos como objetos (stdClass) para {} continuar {} ao gravar (ver AbasRetiradas).
 */
class MoverCartoes
{
    /** Tipos de bloco que todas as áreas mostram (partials de cartão incluídos em todos os blocos.blade.php). */
    public const TIPOS_COMUNS = [
        'texto', 'tabela', 'lista', 'imagem', 'mapa', 'pasta', 'vivo', 'claude', 'criador', 'editorimg',
        'editorpdf', 'editorvid', 'grafico', 'habitos', 'musica', 'questoes', 'rh',
    ];

    public const TIPOS_SO_DA_AREA = [
        'diverso' => ['banco', 'codigo', 'tabletop'],
        'mente'   => ['documento'],
    ];

    /**
     * Abas com quadro de cartões de cada área do sistema (as mesmas de corpo.js, mente.js e carteira.js).
     * O Diverso não está aqui: as abas dele são da pessoa (estado.abas).
     */
    public const ABAS = [
        'corpo' => [
            'tarefas' => 'Tarefas', 'treinos' => 'Treinos', 'cargas' => 'Cargas', 'dietas' => 'Dietas', 'agua' => 'Água',
            'calorias' => 'Calorias', 'metas' => 'Metas', 'livre' => 'Quadro livre',
        ],
        'mente' => [
            'tarefas' => 'Tarefas', 'livros' => 'Livros', 'estudos' => 'Estudos', 'notas' => 'Anotações',
            'faculdade' => 'Faculdade', 'concursos' => 'Concursos', 'livre' => 'Quadro livre',
        ],
        'carteira' => ['tarefas' => 'Tarefas', 'carteira' => 'Carteira', 'invest' => 'Investimentos', 'livre' => 'Quadro livre'],
        // Produtos, Loja, Equipe, Calls e Integrações são páginas comuns, sem quadro de cartões
        'negocios' => ['negocios' => 'Negócios', 'clientes' => 'Clientes', 'docs' => 'Documentos', 'tarefas' => 'Tarefas', 'livre' => 'Quadro livre'],
    ];

    /** Aba principal do Diverso (guarda os cartões de tarefas; diverso.js, SEC). */
    public const SEC_DIVERSO = 'quadro';

    /** Abas por área do Diverso (o Diverso e cada área criada); diverso.js usa o mesmo número. */
    public const MAX_ABAS_GRUPO = 20;

    private const MAX_CHAVE = 60;

    private const FOLGA = 16;

    private int $dono;

    /** Estados carregados: storage => objeto. */
    private array $estados = [];

    /** Estados que mudaram (storage => true). */
    private array $mudou = [];

    /** Chaves a levar de uma tabela para a outra: [deStorage, deChave, paraStorage, paraChave]. */
    private array $chaves = [];

    /** Arquivos do Editor de PDF a levar: [deStorage, paraStorage, arquivo]. */
    private array $pdfs = [];

    private function __construct(private User $u)
    {
        $this->dono = $u->id;
    }

    private static function falha(string $msg): ValidationException
    {
        return ValidationException::withMessages(['mover' => $msg]);
    }

    /* ------------------------------------------------------------------ áreas e abas */

    /** [storage, grupo] de uma área: as áreas criadas guardam no Diverso, com grupo = id. */
    private function resolver(string $area): array
    {
        if (isset(self::ABAS[$area])) return [$area, null];
        if ($area === 'diverso') return ['diverso', null];
        if (preg_match(AreasConta::REGEX_EXTRA, $area) && AreasConta::extra($this->u, $area)) return ['diverso', $area];

        throw self::falha('Área desconhecida.');
    }

    private function estado(string $storage): object
    {
        if (isset($this->estados[$storage])) return $this->estados[$storage];
        [$classe, $prefixo] = Sincronia::armazenamento($storage);
        $valor = $classe::where('user_id', $this->dono)->where('chave', $prefixo . 'estado')->value('valor');
        $e = AbasRetiradas::decodificar($valor) ?? new \stdClass;
        if ($storage === 'diverso') {
            if ($valor !== null && ($e->v ?? null) !== 2) {
                throw self::falha('Abra o Diverso uma vez antes de mover cartões para lá ou de lá (ele atualiza o formato das abas).');
            }
            $e->v = 2;
            $e->abas = is_array($e->abas ?? null) ? $e->abas : [];
        } else {
            AbasRetiradas::migrar($e, $storage);
        }
        $e->layout = isset($e->layout) && is_object($e->layout) ? $e->layout : new \stdClass;
        $e->blocos = is_array($e->blocos ?? null) ? array_values(array_filter($e->blocos, fn ($b) => is_object($b) && isset($b->id))) : [];

        return $this->estados[$storage] = $e;
    }

    /** Nomes dos grupos válidos do Diverso (os ids das áreas criadas da conta). */
    private function gruposValidos(): array
    {
        return array_column(AreasConta::config($this->u)['extras'], 'id');
    }

    /** Grupo de uma aba do Diverso ('' = Diverso; grupo de área que não existe mais também é Diverso). */
    private function grupoDe(object $aba): string
    {
        $g = is_string($aba->grupo ?? null) ? $aba->grupo : '';

        return in_array($g, $this->gruposValidos(), true) ? $g : '';
    }

    /** Abas de uma área: [id => nome]. */
    private function abasDe(string $area): array
    {
        [$storage, $grupo] = $this->resolver($area);
        if ($storage !== 'diverso') return self::ABAS[$storage];
        $saida = [];
        foreach ($this->estado('diverso')->abas as $a) {
            if (is_object($a) && is_string($a->id ?? null) && $this->grupoDe($a) === (string) $grupo) {
                $saida[$a->id] = (string) ($a->nome ?? 'Aba');
            }
        }

        return $saida;
    }

    private function tiposAceitos(string $storage): array
    {
        return array_merge(self::TIPOS_COMUNS, self::TIPOS_SO_DA_AREA[$storage] ?? []);
    }

    /** Destinos para a janela "Mover para outra área": todas as áreas da conta com as abas de cada uma. */
    public static function destinos(User $u): array
    {
        $m = new self($u);
        $saida = [];
        foreach (AreasConta::lista($u) as $slug => $a) {
            try {
                $abas = $m->abasDe($slug);
            } catch (ValidationException) {
                $abas = []; // Diverso em formato antigo: só dá para criar aba nova depois de abrir o Diverso
            }
            $saida[] = [
                'id' => $slug, 'nome' => $a['nome'], 'oculta' => $a['oculta'],
                'quadro' => $slug === 'diverso' || $a['extra'], // aceita aba nova
                'abas' => collect($abas)->map(fn ($n, $id) => ['id' => (string) $id, 'nome' => $n])->values()->all(),
            ];
        }

        return $saida;
    }

    /* ------------------------------------------------------------------ mover */

    /**
     * Move os cartões de uma aba (todos, ou só $ids) para $para / $paraAba (null = aba nova no Diverso ou
     * numa área criada, com o nome $nomeNovo; nas áreas do sistema, o Quadro livre).
     */
    public static function mover(Request $request, User $u, string $de, string $deAba, ?array $ids, string $para, ?string $paraAba, ?string $nomeNovo = null): array
    {
        $m = new self($u);
        $r = $m->moverAba($de, $deAba, $ids, $para, $paraAba, $nomeNovo);
        $m->gravar($request);

        return $r;
    }

    /**
     * Leva TUDO o que dá para levar de uma área para outra (Diverso ou área criada): cada aba com cartões
     * vira uma aba nova no destino. As abas de um Diverso/área criada só trocam de grupo.
     */
    public static function moverTudo(Request $request, User $u, string $de, string $para): array
    {
        $m = new self($u);
        [$sDe, $gDe] = $m->resolver($de);
        [$sPara, $gPara] = $m->resolver($para);
        if ($de === $para) throw self::falha('Escolha outra área de destino.');
        if ($sPara !== 'diverso') throw self::falha('Para levar uma área inteira, escolha o Diverso ou uma área criada por você.');

        $movidos = 0;
        $ficaram = [];
        $abasCriadas = 0;
        if ($sDe === 'diverso') {
            $e = $m->estado('diverso');
            foreach ($e->abas as $a) {
                if (! is_object($a) || $m->grupoDe($a) !== (string) $gDe) continue;
                if ($a->id === self::SEC_DIVERSO) {
                    // a aba principal guarda as tarefas e fica no Diverso: só os cartões dela vão
                    if ($m->blocosDaAba($e, self::SEC_DIVERSO)) {
                        $r = $m->moverAba($de, self::SEC_DIVERSO, null, $para, null, (string) ($a->nome ?? 'Geral'));
                        $movidos += $r['movidos'];
                        $ficaram = array_merge($ficaram, $r['ficaram']);
                        $abasCriadas++;
                    }
                    continue;
                }
                $m->conferirLimite($e, (string) $gPara, 1);
                if ($gPara) $a->grupo = $gPara; else unset($a->grupo);
                $movidos += count($m->blocosDaAba($e, $a->id));
                $abasCriadas++;
            }
            $m->mudou['diverso'] = true;
        } else {
            $e = $m->estado($sDe);
            $abas = self::ABAS[$sDe];
            // blocos em abas que não estão na lista (antigas) também vão
            foreach ($e->blocos as $b) {
                $s = (string) ($b->secao ?? '');
                if ($s !== '' && ! isset($abas[$s])) $abas[$s] = 'Outros';
            }
            $nomeArea = AreasConta::nome($u, $de);
            foreach ($abas as $aba => $nome) {
                if (! $m->blocosDaAba($e, $aba)) continue;
                $r = $m->moverAba($de, $aba, null, $para, null, $nomeArea . ' · ' . $nome);
                $movidos += $r['movidos'];
                $ficaram = array_merge($ficaram, $r['ficaram']);
                if ($r['movidos'] > 0) $abasCriadas++;
            }
        }
        $m->gravar($request);

        return ['movidos' => $movidos, 'ficaram' => $ficaram, 'abas' => $abasCriadas];
    }

    /**
     * Área criada excluída: as abas dela voltam para o Diverso ($apagar = false) ou são apagadas com os cartões e
     * as imagens ($apagar = true).
     */
    public static function soltarGrupo(Request $request, User $u, string $grupo, bool $apagar): int
    {
        $m = new self($u);
        $e = $m->estado('diverso');
        $n = 0;
        $fica = [];
        foreach ($e->abas as $a) {
            if (! is_object($a) || ($a->grupo ?? null) !== $grupo) { $fica[] = $a; continue; }
            $n++;
            if (! $apagar) {
                unset($a->grupo);
                $m->conferirLimite($e, '', 0);
                $fica[] = $a;
                continue;
            }
            $ids = array_map(fn ($b) => (string) $b->id, $m->blocosDaAba($e, $a->id));
            $e->blocos = array_values(array_filter($e->blocos, fn ($b) => ($b->secao ?? null) !== $a->id));
            unset($e->layout->{$a->id});
            foreach ($ids as $id) $m->apagarChavesDe('diverso', $id);
        }
        $e->abas = $fica;
        $m->mudou['diverso'] = true;
        $m->gravar($request);

        return $n;
    }

    private function blocosDaAba(object $e, string $aba): array
    {
        return array_values(array_filter($e->blocos, fn ($b) => ($b->secao ?? null) === $aba));
    }

    private function conferirLimite(object $e, string $grupo, int $novas): void
    {
        $n = 0;
        foreach ($e->abas as $a) {
            if (is_object($a) && $this->grupoDe($a) === $grupo) $n++;
        }
        if ($n + $novas > self::MAX_ABAS_GRUPO) {
            throw self::falha('A área de destino chegou ao limite de ' . self::MAX_ABAS_GRUPO . ' abas. Exclua alguma ou escolha uma aba que já existe.');
        }
    }

    private function moverAba(string $de, string $deAba, ?array $ids, string $para, ?string $paraAba, ?string $nomeNovo): array
    {
        [$sDe, $gDe] = $this->resolver($de);
        [$sPara, $gPara] = $this->resolver($para);
        $E1 = $this->estado($sDe);
        $E2 = $this->estado($sPara);

        $abasDe = $this->abasDe($de);
        $temBloco = (bool) $this->blocosDaAba($E1, $deAba);
        if (! isset($abasDe[$deAba]) && ! $temBloco) throw self::falha('Aba de origem não encontrada.');

        // aba de destino
        if ($paraAba !== null && $paraAba !== '') {
            if (! isset($this->abasDe($para)[$paraAba])) throw self::falha('Aba de destino não encontrada.');
        } elseif ($sPara === 'diverso') {
            $this->conferirLimite($E2, (string) $gPara, 1);
            $nome = mb_substr(trim((string) ($nomeNovo ?: ($abasDe[$deAba] ?? 'Aba'))), 0, 40) ?: 'Aba';
            $paraAba = 'a' . strtolower(base_convert((string) (int) (microtime(true) * 1000), 10, 36)) . bin2hex(random_bytes(2));
            $nova = (object) ['id' => $paraAba, 'nome' => $nome, 'icone' => 'pasta'];
            if ($gPara) $nova->grupo = $gPara;
            $E2->abas[] = $nova;
            $E2->layout->$paraAba = new \stdClass;
        } else {
            $paraAba = 'livre';
        }
        if ($sDe === $sPara && $deAba === $paraAba) throw self::falha('Os cartões já estão nessa aba.');

        // quais blocos: os pedidos (com o que estiver dentro das pastas pedidas) ou a aba toda
        $daAba = $this->blocosDaAba($E1, $deAba);
        if ($ids !== null) {
            $sel = array_flip(array_map('strval', $ids));
            do {
                $antes = count($sel);
                foreach ($daAba as $b) {
                    if (isset($b->pasta) && isset($sel[(string) $b->pasta])) $sel[(string) $b->id] = true;
                }
            } while (count($sel) > $antes);
            $daAba = array_values(array_filter($daAba, fn ($b) => isset($sel[(string) $b->id])));
        }
        if (! $daAba) throw self::falha('Não há cartões para mover nessa aba.');

        $aceitos = $this->tiposAceitos($sPara);
        $vao = [];
        $ficaram = [];
        foreach ($daAba as $b) {
            if (in_array($b->tipo ?? '', $aceitos, true)) $vao[] = $b;
            else $ficaram[] = ['titulo' => (string) ($b->titulo ?? 'Cartão'), 'tipo' => (string) ($b->tipo ?? '')];
        }

        // ids que já existem no destino (blocos e cartões do layout): quem colidir ganha id novo
        $usados = [];
        foreach ($E2->blocos as $b) $usados[(string) $b->id] = true;
        foreach (get_object_vars($E2->layout) as $lay) {
            if (is_object($lay)) foreach (array_keys(get_object_vars($lay)) as $k) $usados[(string) $k] = true;
        }
        $mesmo = $sDe === $sPara;
        $novoId = [];
        foreach ($vao as $b) {
            $id = (string) $b->id;
            if (! $mesmo && isset($usados[$id])) {
                do { $n = 'b' . strtolower(base_convert((string) (int) (microtime(true) * 1000), 10, 36)) . bin2hex(random_bytes(2)); } while (isset($usados[$n]));
                $novoId[$id] = $n;
                $usados[$n] = true;
            } else {
                $novoId[$id] = $id;
            }
        }

        // chaves das imagens: confere se cabem no destino (o prefixo da Mente ocupa 6 caracteres)
        [$cDe, $pDe] = Sincronia::armazenamento($sDe);
        [, $pPara] = Sincronia::armazenamento($sPara);
        $chavesDe = [];
        foreach ($vao as $i => $b) {
            $id = (string) $b->id;
            $ks = $this->chavesDoBloco($cDe, $pDe, $id);
            foreach ($ks as $k) {
                $sufixo = substr($k, strlen($pDe . 'img.' . $id));
                if (strlen($pPara . 'img.' . $novoId[$id] . $sufixo) > self::MAX_CHAVE) {
                    $ficaram[] = ['titulo' => (string) ($b->titulo ?? 'Cartão'), 'tipo' => (string) ($b->tipo ?? '')];
                    unset($vao[$i]);
                    continue 2;
                }
            }
            $chavesDe[$id] = $ks;
        }
        $vao = array_values($vao);
        if (! $vao) {
            return ['movidos' => 0, 'ficaram' => $ficaram, 'aba' => $paraAba, 'area' => $para];
        }

        // layout: os cartões entram abaixo do que já existe na aba de destino, com as posições relativas de antes
        $layDe = isset($E1->layout->$deAba) && is_object($E1->layout->$deAba) ? $E1->layout->$deAba : new \stdClass;
        $layPara = isset($E2->layout->$paraAba) && is_object($E2->layout->$paraAba) ? $E2->layout->$paraAba : new \stdClass;
        $presentes = array_map(fn ($b) => (string) $b->id, $this->blocosDaAba($E2, $paraAba));
        $fundoPara = $this->fundoAba($sPara, $paraAba, $layPara, $presentes);
        $rects = [];
        $alt = 0;
        foreach ($vao as $b) {
            $id = (string) $b->id;
            $r = isset($layDe->$id) && is_object($layDe->$id) ? clone $layDe->$id : (object) ['x' => 0, 'y' => $alt, 'w' => 360, 'h' => 260, 'z' => 1];
            $r->y = is_numeric($r->y ?? null) ? (float) $r->y : 0;
            $r->h = is_numeric($r->h ?? null) ? (float) $r->h : 260;
            $rects[$id] = $r;
            $alt = max($alt, $r->y + $r->h);
        }
        $topo = min(array_map(fn ($r) => $r->y, $rects));
        $inicio = $fundoPara > 0 ? $fundoPara + self::FOLGA : 0;
        $movidosIds = array_flip(array_keys($rects));

        foreach ($vao as $b) {
            $id = (string) $b->id;
            $r = $rects[$id];
            $r->y = (int) round($r->y - $topo + $inicio);
            $r->h = (int) round($r->h);
            unset($layDe->$id);
            $layPara->{$novoId[$id]} = $r;

            $b->id = $novoId[$id];
            $b->secao = $paraAba;
            if (isset($b->pasta)) {
                if (isset($movidosIds[(string) $b->pasta])) $b->pasta = $novoId[(string) $b->pasta];
                else unset($b->pasta); // a pasta ficou para trás: o cartão fica solto na aba nova
            }
            // vínculos (tarefas, negócios, documentos) são da área de origem
            if (! $mesmo && isset($b->links)) $b->links = [];

            if (! $mesmo || $novoId[$id] !== $id) {
                foreach ($chavesDe[$id] as $k) {
                    $sufixo = substr($k, strlen($pDe . 'img.' . $id));
                    $this->chaves[] = [$sDe, $k, $sPara, $pPara . 'img.' . $novoId[$id] . $sufixo];
                }
            }
            if (! $mesmo && ($b->tipo ?? '') === 'editorpdf') {
                $fontes = $b->dados->pdf->fontes ?? null;
                if (is_object($fontes)) {
                    foreach (get_object_vars($fontes) as $f) {
                        if (is_object($f) && is_string($f->id ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/', $f->id)) $this->pdfs[] = [$sDe, $sPara, $f->id];
                    }
                }
            }
        }

        $E1->blocos = array_values(array_filter($E1->blocos, fn ($b) => ! in_array($b, $vao, true)));
        if ($mesmo) {
            $E1->blocos = array_merge($E1->blocos, $vao);
        } else {
            $E2->blocos = array_merge($E2->blocos, $vao);
        }
        $E1->layout->$deAba = $layDe;
        $E2->layout->$paraAba = $layPara;
        $this->mudou[$sDe] = true;
        $this->mudou[$sPara] = true;

        return ['movidos' => count($vao), 'ficaram' => $ficaram, 'aba' => $paraAba, 'area' => $para];
    }

    /** Fundo da aba de destino, contando blocos e cartões fixos que já estão lá. */
    private function fundoAba(string $storage, string $aba, object $lay, array $blocos): float
    {
        // contam os blocos da aba e tudo do layout que não é bloco (cartões fixos, como as tarefas). Entradas de
        // blocos apagados também contam: é melhor sobrar um espaço do que cobrir um cartão
        $ids = [];
        $todosBlocos = array_map(fn ($b) => (string) $b->id, $this->estados[$storage]->blocos);
        foreach (array_keys(get_object_vars($lay)) as $k) {
            $k = (string) $k;
            if (in_array($k, $blocos, true) || ! in_array($k, $todosBlocos, true)) $ids[] = $k;
        }

        return AbasRetiradas::fundo($lay, $ids);
    }

    /** Chaves guardadas de um bloco (img.<id> e img.<id>.*), com o prefixo da área. */
    private function chavesDoBloco(string $classe, string $prefixo, string $id): array
    {
        $base = $prefixo . 'img.' . $id;
        $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base);

        return $classe::where('user_id', $this->dono)
            ->where(fn ($q) => $q->where('chave', $base)->orWhere('chave', 'like', $esc . '.%'))
            ->pluck('chave')
            ->filter(fn ($k) => $k === $base || str_starts_with($k, $base . '.'))
            ->values()->all();
    }

    private function apagarChavesDe(string $storage, string $id): void
    {
        [$classe, $prefixo] = Sincronia::armazenamento($storage);
        $ks = $this->chavesDoBloco($classe, $prefixo, $id);
        if ($ks) $classe::where('user_id', $this->dono)->whereIn('chave', $ks)->delete();
        if ($storage === 'diverso') {
            // pedaços do cartão Banco de dados
            $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], \App\Http\Controllers\DiversoController::PEDACO . $id);
            $classe::where('user_id', $this->dono)->where('chave', 'like', $esc . '.%')->delete();
        }
    }

    /* ------------------------------------------------------------------ gravar */

    private function gravar(Request $request): void
    {
        DB::transaction(function () {
            foreach ($this->chaves as [$sDe, $kDe, $sPara, $kPara]) {
                [$cDe] = Sincronia::armazenamento($sDe);
                [$cPara] = Sincronia::armazenamento($sPara);
                $valor = $cDe::where('user_id', $this->dono)->where('chave', $kDe)->value('valor');
                if ($valor === null) continue;
                $cPara::updateOrCreate(['user_id' => $this->dono, 'chave' => $kPara], ['valor' => $valor]);
                if (! ($cDe === $cPara && $kDe === $kPara)) $cDe::where('user_id', $this->dono)->where('chave', $kDe)->delete();
            }
            foreach (array_keys($this->mudou) as $s) {
                [$classe, $prefixo] = Sincronia::armazenamento($s);
                $classe::updateOrCreate(
                    ['user_id' => $this->dono, 'chave' => $prefixo . 'estado'],
                    ['valor' => AbasRetiradas::codificar($this->estados[$s])]
                );
            }
        });

        // PDFs do Editor de PDF: o arquivo muda de pasta junto com o cartão
        $disco = Storage::disk('local');
        foreach ($this->pdfs as [$sDe, $sPara, $arq]) {
            $de = "editorpdf/{$this->dono}/$sDe/$arq.pdf";
            $para = "editorpdf/{$this->dono}/$sPara/$arq.pdf";
            if ($de !== $para && $disco->exists($de) && ! $disco->exists($para)) $disco->move($de, $para);
        }

        foreach (array_keys($this->mudou) as $s) {
            Sincronia::avisarMudanca($request, $s, $this->dono, AbasRetiradas::codificar($this->estados[$s]));
        }
    }
}
