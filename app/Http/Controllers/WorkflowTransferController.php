<?php

namespace App\Http\Controllers;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
use App\Support\Planos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Exportar e importar as áreas do Work Flower entre contas.
 *
 * Onde cada área guarda os dados:
 *   corpo    -> corpo_dados   (chaves sem prefixo, exceto as listadas em PREFIXOS_FORA_DO_CORPO)
 *   mente    -> corpo_dados   (chaves "mente.*")  + PDFs em storage/app/mente/pdf/{usuario}/{id}.pdf
 *   carteira -> carteira_dados
 *   diverso  -> diverso_dados
 *
 * Fora do arquivo ficam: tema e fundo da página ("fundo", "fundo.img") e a biblioteca.
 *
 * IMPORTAR SUBSTITUI o conteúdo atual de cada área que vier no arquivo.
 */
class WorkflowTransferController extends Controller
{
    private const AREAS = [
        'corpo'    => 'Corpo',
        'mente'    => 'Mente',
        'carteira' => 'Carteira',
        'diverso'  => 'Diverso',
    ];

    private const PREFIXO_MENTE = 'mente.';

    /** Chaves de corpo_dados que NÃO pertencem à área Corpo. */
    private const PREFIXOS_FORA_DO_CORPO = ['mente.', 'fundo', 'biblioteca', 'preferencias'];

    private const MAX_LINHAS_POR_AREA = 5000;
    private const MAX_VALOR           = 8000000;           // mesmo limite do MenteController
    private const MAX_PDF_EXPORTA     = 20 * 1024 * 1024;  // soma dos PDFs que vão no arquivo
    private const MAX_PDF_IMPORTA     = 30 * 1024 * 1024;
    private const MAX_PDF_ARQUIVO     = 40 * 1024 * 1024;

    private const REGEX_CHAVE       = '/^[A-Za-z0-9._-]{1,60}$/';
    private const REGEX_CHAVE_MENTE = '/^[A-Za-z0-9._-]{1,100}$/';
    private const REGEX_ID_PDF      = '/^[A-Za-z0-9_-]{1,100}$/';

    // ---------------------------------------------------------------- lista

    /** Áreas disponíveis para exportar, com um resumo do que há em cada uma. */
    public function lista(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $itens = [];

        foreach (self::AREAS as $slug => $nome) {
            $n = $this->consulta($slug, $uid)->count();
            $resumo = $n . ($n === 1 ? ' registro' : ' registros');

            if ($slug === 'mente') {
                $p = count($this->arquivosPdf($uid));
                if ($p > 0) $resumo .= ' · ' . $p . ($p === 1 ? ' PDF' : ' PDFs');
            }

            $itens[] = ['id' => $slug, 'nome' => $nome, 'resumo' => $resumo];
        }

        return response()->json($itens);
    }

    // ------------------------------------------------------------- exportar

    public function exportar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:4'],
            'ids.*' => ['string', 'in:corpo,mente,carteira,diverso'],
        ]);

        $uid     = $request->user()->id;
        $pedidas = array_unique($dados['ids']);
        $saida   = [];
        $avisos  = [];

        // Limites do plano (antes de qualquer leitura pesada)
        $slugPlano = Planos::slugDe($request->user());
        Planos::exigir($slugPlano, 'workflows', count($pedidas), 'exportar');
        Planos::exigir($slugPlano, 'elementos', $this->elementosDasAreas($pedidas, $uid), 'exportar');

        foreach (array_keys(self::AREAS) as $slug) {
            if (! in_array($slug, $pedidas, true)) continue;

            $linhas = $this->consulta($slug, $uid)
                ->orderBy('chave')
                ->get(['chave', 'valor'])
                ->map(fn ($l) => [
                    'chave' => $slug === 'mente' ? substr($l->chave, strlen(self::PREFIXO_MENTE)) : $l->chave,
                    'valor' => $l->valor,
                ])
                ->values()
                ->all();

            $item = ['area' => $slug, 'nome' => self::AREAS[$slug], 'dados' => $linhas];

            if ($slug === 'mente') {
                [$pdfs, $omitidos] = $this->lerPdfs($uid);
                $item['pdfs'] = $pdfs;
                if ($omitidos > 0) {
                    $avisos[] = $omitidos . ($omitidos === 1 ? ' PDF da Mente não coube' : ' PDFs da Mente não couberam')
                              . ' no arquivo (limite de ' . (self::MAX_PDF_EXPORTA / 1024 / 1024) . ' MB) e ficou de fora.';
                }
            }

            $saida[] = $item;
        }

        return response()->json(
            ['workflows' => $saida, 'avisos' => $avisos],
            200,
            [],
            JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    // ------------------------------------------------------------- importar

    public function importar(Request $request): JsonResponse
    {
        $request->validate([
            'formato'   => ['required', 'in:work-flower.workflows'],
            'versao'    => ['required', 'integer', 'min:1', 'max:1'],
            'workflows' => ['required', 'array', 'min:1', 'max:4'],
        ]);

        // Valida e limpa tudo ANTES de tocar no banco: se algo estiver errado, nada é alterado.
        $pacotes  = [];
        $vistas   = [];
        $totalPdf = 0;

        foreach ($request->input('workflows') as $w) {
            $p = $this->validarArea($w, $totalPdf);
            if (isset($vistas[$p['area']])) {
                $this->falha('A área ' . self::AREAS[$p['area']] . ' aparece duas vezes no arquivo.');
            }
            $vistas[$p['area']] = true;
            $pacotes[] = $p;
        }

        // Limites do plano: valem para o que vem no arquivo, antes de tocar no banco
        $slugPlano = Planos::slugDe($request->user());
        Planos::exigir($slugPlano, 'workflows', count($pacotes), 'importar');
        $totalElementos = 0;
        foreach ($pacotes as $p) {
            foreach ($p['dados'] as $l) {
                $totalElementos += Planos::contarElementos($l['valor']);
            }
        }
        Planos::exigir($slugPlano, 'elementos', $totalElementos, 'importar');

        $uid = $request->user()->id;

        DB::transaction(function () use ($pacotes, $uid) {
            $agora = now();

            foreach ($pacotes as $p) {
                $area = $p['area'];
                $this->consulta($area, $uid)->delete();

                $prefixo = $area === 'mente' ? self::PREFIXO_MENTE : '';
                $modelo  = $this->modelo($area);
                foreach (array_chunk($p['dados'], 50) as $bloco) {
                    $linhas = array_map(fn ($l) => [
                        'user_id'    => $uid,
                        'chave'      => $prefixo . $l['chave'],
                        'valor'      => $l['valor'],
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ], $bloco);

                    $modelo::insert($linhas);
                }
            }
        });

        // Arquivos só depois de o banco ter confirmado
        foreach ($pacotes as $p) {
            foreach ($p['pdfs'] as $pdf) {
                Storage::disk('local')->put('mente/pdf/' . $uid . '/' . $pdf['id'] . '.pdf', $pdf['conteudo']);
            }
        }

        return response()->json([
            'importados' => count($pacotes),
            'areas'      => array_map(fn ($p) => self::AREAS[$p['area']], $pacotes),
        ]);
    }

    // ------------------------------------------------------------ validação

    /** Confere uma área do arquivo e devolve só o que é seguro gravar. */
    private function validarArea($w, int &$totalPdf): array
    {
        if (! is_array($w)) $this->falha('O arquivo está malformado.');

        $area = $w['area'] ?? null;
        if (! is_string($area) || ! isset(self::AREAS[$area])) $this->falha('O arquivo tem uma área desconhecida.');

        $nomeArea = self::AREAS[$area];
        $linhas   = $w['dados'] ?? [];
        if (! is_array($linhas) || count($linhas) > self::MAX_LINHAS_POR_AREA) {
            $this->falha("Os dados de {$nomeArea} estão malformados ou são grandes demais.");
        }

        $regex  = $area === 'mente' ? self::REGEX_CHAVE_MENTE : self::REGEX_CHAVE;
        $limpas = [];
        $vistas = [];

        foreach ($linhas as $l) {
            $chave = is_array($l) ? ($l['chave'] ?? null) : null;
            $valor = is_array($l) ? ($l['valor'] ?? null) : null;

            if (! is_string($chave) || ! is_string($valor)) $this->falha("Há um registro malformado em {$nomeArea}.");
            if (! preg_match($regex, $chave)) $this->falha("Há um registro de {$nomeArea} com nome inválido.");
            if (strlen($valor) > self::MAX_VALOR) $this->falha("Há um registro de {$nomeArea} grande demais.");

            // O arquivo não pode escrever em chaves de outras áreas nem no tema
            if ($area === 'corpo') {
                foreach (self::PREFIXOS_FORA_DO_CORPO as $proibido) {
                    if (strpos($chave, $proibido) === 0) {
                        $this->falha("Há um registro de {$nomeArea} com nome não permitido.");
                    }
                }
            }

            if (isset($vistas[$chave])) continue;
            $vistas[$chave] = true;
            $limpas[] = ['chave' => $chave, 'valor' => $valor];
        }

        $pdfs = [];
        if ($area === 'mente') {
            $lista = $w['pdfs'] ?? [];
            if (! is_array($lista)) $this->falha('Os PDFs da Mente estão malformados.');

            foreach ($lista as $pdf) {
                $id  = is_array($pdf) ? ($pdf['id'] ?? null) : null;
                $b64 = is_array($pdf) ? ($pdf['base64'] ?? null) : null;

                if (! is_string($id) || ! preg_match(self::REGEX_ID_PDF, $id) || ! is_string($b64)) {
                    $this->falha('Há um PDF da Mente malformado.');
                }

                $bin = base64_decode($b64, true);
                if ($bin === false || strpos($bin, '%PDF-') !== 0 || strlen($bin) > self::MAX_PDF_ARQUIVO) {
                    $this->falha('Há um PDF da Mente inválido ou grande demais.');
                }

                $totalPdf += strlen($bin);
                if ($totalPdf > self::MAX_PDF_IMPORTA) $this->falha('Os PDFs do arquivo passam do limite permitido.');

                $pdfs[$id] = ['id' => $id, 'conteudo' => $bin];
            }
        }

        return ['area' => $area, 'dados' => $limpas, 'pdfs' => array_values($pdfs)];
    }

    private function falha(string $mensagem)
    {
        throw ValidationException::withMessages(['workflows' => [$mensagem]]);
    }

    // -------------------------------------------------------------- dados

    private function modelo(string $area): string
    {
        switch ($area) {
            case 'carteira': return CarteiraDado::class;
            case 'diverso':  return DiversoDado::class;
            default:         return CorpoDado::class; // corpo e mente
        }
    }

    /** Linhas do usuário que pertencem à área. */
    private function consulta(string $area, int $uid)
    {
        $modelo = $this->modelo($area);
        $q = $modelo::where('user_id', $uid);

        if ($area === 'mente') {
            return $q->where('chave', 'like', self::PREFIXO_MENTE . '%');
        }

        if ($area === 'corpo') {
            return $q->where(function ($sub) {
                foreach (self::PREFIXOS_FORA_DO_CORPO as $prefixo) {
                    $sub->where('chave', 'not like', $prefixo . '%');
                }
            });
        }

        return $q;
    }

    /** Quantos elementos (texto, tabela etc.) há nas áreas pedidas. */
    private function elementosDasAreas(array $areas, int $uid): int
    {
        $total = 0;
        foreach ($areas as $slug) {
            foreach ($this->consulta($slug, $uid)->select('valor')->cursor() as $linha) {
                $total += Planos::contarElementos($linha->valor);
            }
        }
        return $total;
    }

    // ---------------------------------------------------------------- PDFs

    /** Caminhos dos PDFs da Mente do usuário. */
    private function arquivosPdf(int $uid): array
    {
        try {
            $arquivos = Storage::disk('local')->files('mente/pdf/' . $uid);
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter($arquivos, fn ($f) => substr(strtolower($f), -4) === '.pdf'));
    }

    /** PDFs em base64, até o limite de tamanho. Devolve [lista, quantos ficaram de fora]. */
    private function lerPdfs(int $uid): array
    {
        $lista     = [];
        $omitidos  = 0;
        $acumulado = 0;

        foreach ($this->arquivosPdf($uid) as $caminho) {
            $tamanho = Storage::disk('local')->size($caminho);

            if ($acumulado + $tamanho > self::MAX_PDF_EXPORTA) {
                $omitidos++;
                continue;
            }

            $acumulado += $tamanho;
            $lista[] = [
                'id'     => pathinfo($caminho, PATHINFO_FILENAME),
                'base64' => base64_encode(Storage::disk('local')->get($caminho)),
            ];
        }

        return [$lista, $omitidos];
    }
}
