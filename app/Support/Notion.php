<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * API do Notion pela REST (sem SDK), para Negócios › Integrações › Notion.
 *
 * Conexão: integração pública do dono do site (OAuth; NOTION_CLIENT_ID/SECRET no .env) ou token de integração
 * interna colado pela pessoa. Em ambos os casos o Notion só mostra as páginas que a pessoa compartilhou com a
 * integração. As páginas viram Markdown (cartões de texto) e os bancos de dados viram tabelas.
 *
 * O retorno do OAuth traz só code e state (sem endereços de outros sites), então não precisa da página de repasse
 * usada no Google (docs/google): o mod_security da HostGator aceita.
 */
class Notion
{
    public const API = 'https://api.notion.com/v1';
    public const VERSAO = '2022-06-28';

    /** Limites por pedido, para caber no tempo da hospedagem compartilhada. */
    public const MAX_BLOCOS = 800;
    public const MAX_PROFUNDIDADE = 4;
    public const MAX_LINHAS = 300;

    private int $blocosLidos = 0;

    public function __construct(private string $token)
    {
    }

    /* ---------------- OAuth ---------------- */

    public static function oauthConfigurado(): bool
    {
        return (string) config('services.notion.client_id') !== '' && (string) config('services.notion.client_secret') !== '';
    }

    public static function retorno(): string
    {
        return (string) (config('services.notion.redirect') ?: route('integracoes.notion.retorno'));
    }

    public static function urlAutorizacao(string $estado): string
    {
        return self::API . '/oauth/authorize?' . http_build_query([
            'client_id'     => config('services.notion.client_id'),
            'response_type' => 'code',
            'owner'         => 'user',
            'redirect_uri'  => self::retorno(),
            'state'         => $estado,
        ]);
    }

    /** Troca o código do retorno pelo token. Devolve access_token, workspace_id, workspace_nome e bot_id. */
    public static function trocarCodigo(string $codigo): array
    {
        $r = Http::withBasicAuth((string) config('services.notion.client_id'), (string) config('services.notion.client_secret'))
            ->acceptJson()->asJson()->timeout(30)
            ->withHeaders(['Notion-Version' => self::VERSAO])
            ->post(self::API . '/oauth/token', [
                'grant_type'   => 'authorization_code',
                'code'         => $codigo,
                'redirect_uri' => self::retorno(),
            ]);
        if (! $r->successful() || ! is_string($r->json('access_token'))) {
            throw new RuntimeException('O Notion não liberou o acesso. Tente conectar de novo.');
        }

        return [
            'access_token'   => $r->json('access_token'),
            'workspace_id'   => self::curto($r->json('workspace_id'), 60),
            'workspace_nome' => self::curto($r->json('workspace_name'), 120),
            'bot_id'         => self::curto($r->json('bot_id'), 60),
        ];
    }

    /** Token de integração interna: começa com "secret_" (antigo) ou "ntn_". */
    public static function tokenValido(string $token): bool
    {
        return (bool) preg_match('/^(secret_|ntn_)[A-Za-z0-9]{20,120}$/', $token);
    }

    /* ---------------- chamadas ---------------- */

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)->acceptJson()->asJson()->timeout(30)
            ->withHeaders(['Notion-Version' => self::VERSAO]);
    }

    private function conferir(Response $r): array
    {
        if ($r->successful()) {
            return (array) $r->json();
        }
        $codigo = (string) $r->json('code');
        throw new NotionErro(match (true) {
            $r->status() === 401 => 'O Notion recusou o acesso (a conexão foi removida ou o token mudou). Conecte de novo.',
            $r->status() === 404 || $codigo === 'object_not_found' => 'Página não encontrada. No Notion, compartilhe a página com a integração (••• › Conexões).',
            $r->status() === 429 => 'O Notion pediu uma pausa (muitos pedidos). Tente de novo em um minuto.',
            default => 'O Notion respondeu com erro (' . $r->status() . '). Tente de novo.',
        }, $r->status());
    }

    /** Quem é a integração (confere o token). Devolve o nome do espaço de trabalho, se vier. */
    public function eu(): array
    {
        $j = $this->conferir($this->http()->get(self::API . '/users/me'));

        return [
            'bot_id'         => self::curto($j['id'] ?? null, 60),
            'workspace_nome' => self::curto(data_get($j, 'bot.workspace_name') ?: ($j['name'] ?? null), 120),
        ];
    }

    /**
     * Páginas e bancos de dados que a integração enxerga, os editados por último primeiro.
     * Cada item: id, tipo (pagina|banco), titulo, editado, url, pai (id da página/banco pai ou null).
     */
    public function buscar(string $texto = '', int $max = 200): array
    {
        $saida = [];
        $cursor = null;
        do {
            $corpo = ['page_size' => 100, 'sort' => ['direction' => 'descending', 'timestamp' => 'last_edited_time']];
            if ($texto !== '') $corpo['query'] = $texto;
            if ($cursor) $corpo['start_cursor'] = $cursor;
            $j = $this->conferir($this->http()->post(self::API . '/search', $corpo));
            foreach ((array) ($j['results'] ?? []) as $o) {
                if (! is_array($o) || ! empty($o['archived']) || ! empty($o['in_trash'])) continue;
                $saida[] = $this->resumo($o);
            }
            $cursor = ! empty($j['has_more']) ? ($j['next_cursor'] ?? null) : null;
        } while ($cursor && count($saida) < $max);

        return array_slice($saida, 0, $max);
    }

    private function resumo(array $o): array
    {
        $banco = ($o['object'] ?? '') === 'database';
        $pai = $o['parent'] ?? [];

        return [
            'id'      => (string) ($o['id'] ?? ''),
            'tipo'    => $banco ? 'banco' : 'pagina',
            'titulo'  => $banco ? self::texto($o['title'] ?? []) : self::tituloPagina($o),
            'icone'   => data_get($o, 'icon.type') === 'emoji' ? (string) data_get($o, 'icon.emoji') : null,
            'editado' => (string) ($o['last_edited_time'] ?? ''),
            'url'     => (string) ($o['url'] ?? ''),
            'pai'     => $pai['page_id'] ?? $pai['database_id'] ?? null,
        ];
    }

    /**
     * Conteúdo para virar cartão. Página: { tipo: pagina, texto (Markdown) }; banco: { tipo: banco, cab, linhas }.
     * $tipo vem da lista (o navegador sabe se é página ou banco); sem ele, tenta página e depois banco.
     */
    public function conteudo(string $id, ?string $tipo = null): array
    {
        if ($tipo !== 'banco') {
            try {
                $p = $this->conferir($this->http()->get(self::API . '/pages/' . $id));

                return $this->resumo($p) + ['texto' => $this->markdownDaPagina($p)];
            } catch (NotionErro $e) {
                if ($tipo === 'pagina' || $e->status !== 404) throw $e;
            }
        }
        $d = $this->conferir($this->http()->get(self::API . '/databases/' . $id));

        return $this->resumo($d) + $this->tabela($d);
    }

    /* ---------------- página → Markdown ---------------- */

    private function markdownDaPagina(array $p): string
    {
        $this->blocosLidos = 0;
        $md = '';
        // propriedades (quando a página é uma linha de banco de dados), menos o título, viram uma lista no topo
        $props = [];
        foreach ((array) ($p['properties'] ?? []) as $nome => $v) {
            if (! is_array($v) || ($v['type'] ?? '') === 'title') continue;
            $t = self::valorPropriedade($v);
            if ($t !== '') $props[] = '- **' . $nome . ':** ' . $t;
        }
        if ($props) $md .= implode("\n", $props) . "\n\n";

        $md .= $this->blocos((string) $p['id'], 0);
        if ($this->blocosLidos >= self::MAX_BLOCOS) {
            $md .= "\n\n> Página longa: o resto ficou no Notion.";
        }

        return rtrim($md) . "\n";
    }

    /** Filhos de um bloco (ou página), já em Markdown, com $nivel de recuo para listas. */
    private function blocos(string $id, int $nivel): string
    {
        $linhas = [];
        $cursor = null;
        $num = 0;
        do {
            $q = ['page_size' => 100] + ($cursor ? ['start_cursor' => $cursor] : []);
            $j = $this->conferir($this->http()->get(self::API . '/blocks/' . $id . '/children', $q));
            foreach ((array) ($j['results'] ?? []) as $b) {
                if (! is_array($b) || $this->blocosLidos >= self::MAX_BLOCOS) break 2;
                $this->blocosLidos++;
                $tipo = (string) ($b['type'] ?? '');
                $num = $tipo === 'numbered_list_item' ? $num + 1 : 0;
                $linhas[] = $this->bloco($b, $tipo, $nivel, $num);
            }
            $cursor = ! empty($j['has_more']) ? ($j['next_cursor'] ?? null) : null;
        } while ($cursor);

        // listas ficam juntas; o resto separado por linha em branco
        $md = '';
        $antes = null;
        foreach ($linhas as [$t, $lista]) {
            if ($t === null) continue;
            if ($antes !== null) $md .= ($lista && $antes) ? "\n" : "\n\n";
            $md .= $t;
            $antes = $lista;
        }

        return $md;
    }

    /** Um bloco em Markdown: [texto, é item de lista?]. */
    private function bloco(array $b, string $tipo, int $nivel, int $num): array
    {
        $d = (array) ($b[$tipo] ?? []);
        $rt = self::rico($d['rich_text'] ?? []);
        $recuo = str_repeat('    ', $nivel);
        $filhos = fn (int $n) => (! empty($b['has_children']) && $nivel < self::MAX_PROFUNDIDADE) ? $this->blocos((string) $b['id'], $n) : '';
        $comFilhos = function (string $linha, bool $lista) use ($filhos, $nivel) {
            $f = $filhos($lista ? $nivel + 1 : $nivel);

            return [$f !== '' ? $linha . ($lista ? "\n" : "\n\n") . $f : $linha, $lista];
        };

        return match ($tipo) {
            'paragraph'          => $comFilhos($recuo . $rt, false),
            'heading_1'          => [$recuo . '# ' . $rt, false],
            'heading_2'          => [$recuo . '## ' . $rt, false],
            'heading_3'          => [$recuo . '### ' . $rt, false],
            'bulleted_list_item' => $comFilhos($recuo . '- ' . $rt, true),
            'numbered_list_item' => $comFilhos($recuo . $num . '. ' . $rt, true),
            'to_do'              => $comFilhos($recuo . '- [' . (! empty($d['checked']) ? 'x' : ' ') . '] ' . $rt, true),
            'toggle'             => $comFilhos($recuo . '- ' . $rt, true),
            'quote'              => [$recuo . '> ' . str_replace("\n", "\n" . $recuo . '> ', $rt), false],
            'callout'            => [$recuo . '> ' . (data_get($d, 'icon.emoji') ? data_get($d, 'icon.emoji') . ' ' : '') . str_replace("\n", "\n" . $recuo . '> ', $rt), false],
            'code'               => [$recuo . '```' . preg_replace('/[^a-z0-9#+-]/', '', strtolower((string) ($d['language'] ?? ''))) . "\n" . self::texto($d['rich_text'] ?? []) . "\n" . $recuo . '```', false],
            'equation'           => [$recuo . '$$' . ($d['expression'] ?? '') . '$$', false],
            'divider'            => [$recuo . '---', false],
            'image', 'file', 'pdf', 'video', 'audio' => [$recuo . $this->arquivo($tipo, $d), false],
            'bookmark', 'embed', 'link_preview' => [$recuo . self::link((string) ($d['url'] ?? ''), self::rico($d['caption'] ?? []) ?: (string) ($d['url'] ?? '')), false],
            'child_page'         => [$recuo . '📄 [[' . ($d['title'] ?? 'Página') . ']]', false],
            'child_database'     => [$recuo . '🗃 ' . ($d['title'] ?? 'Banco de dados') . ' (importe o banco de dados à parte)', false],
            'table'              => [$this->tabelaDeBlocos($b, $recuo), false],
            'column_list', 'column', 'synced_block' => [$filhos($nivel), false],
            'table_of_contents', 'breadcrumb' => [null, false],
            default              => [$rt !== '' ? $recuo . $rt : null, false],
        };
    }

    private function arquivo(string $tipo, array $d): string
    {
        $url = (string) (data_get($d, 'external.url') ?: data_get($d, 'file.url') ?: '');
        $legenda = self::rico($d['caption'] ?? []) ?: ($d['name'] ?? $tipo);
        // os links de arquivos guardados no Notion valem só 1 hora: fica o nome, não um link quebrado
        if (data_get($d, 'type') === 'file') {
            return ($tipo === 'image' ? '🖼 ' : '📎 ') . $legenda . ' (arquivo no Notion)';
        }

        return $tipo === 'image' ? '![' . $legenda . '](' . $url . ')' : self::link($url, $legenda);
    }

    private function tabelaDeBlocos(array $b, string $recuo): string
    {
        $j = $this->conferir($this->http()->get(self::API . '/blocks/' . $b['id'] . '/children', ['page_size' => 100]));
        $linhas = [];
        foreach ((array) ($j['results'] ?? []) as $r) {
            $this->blocosLidos++;
            $cel = array_map(fn ($c) => str_replace(['|', "\n"], ['\|', ' '], self::rico($c)), (array) data_get($r, 'table_row.cells', []));
            $linhas[] = $recuo . '| ' . implode(' | ', $cel) . ' |';
        }
        if (! $linhas) return '';
        $n = count((array) data_get($j, 'results.0.table_row.cells', []));
        array_splice($linhas, 1, 0, [$recuo . '|' . str_repeat(' --- |', max(1, $n))]);

        return implode("\n", $linhas);
    }

    /* ---------------- banco de dados → tabela ---------------- */

    private function tabela(array $d): array
    {
        $cab = [];
        $tituloCol = null;
        foreach ((array) ($d['properties'] ?? []) as $nome => $p) {
            if (($p['type'] ?? '') === 'title') $tituloCol = $nome;
            else $cab[] = $nome;
        }
        if ($tituloCol !== null) array_unshift($cab, $tituloCol);

        $linhas = [];
        $cursor = null;
        do {
            $corpo = ['page_size' => 100] + ($cursor ? ['start_cursor' => $cursor] : []);
            $j = $this->conferir($this->http()->post(self::API . '/databases/' . $d['id'] . '/query', $corpo));
            foreach ((array) ($j['results'] ?? []) as $pg) {
                $props = (array) ($pg['properties'] ?? []);
                $linhas[] = array_map(fn ($c) => isset($props[$c]) ? self::valorPropriedade($props[$c]) : '', $cab);
            }
            $cursor = ! empty($j['has_more']) ? ($j['next_cursor'] ?? null) : null;
        } while ($cursor && count($linhas) < self::MAX_LINHAS);

        return ['cab' => $cab ?: ['Nome'], 'linhas' => array_slice($linhas, 0, self::MAX_LINHAS) ?: [array_fill(0, max(1, count($cab)), '')]];
    }

    /** Valor de uma propriedade de página/linha em texto simples. */
    public static function valorPropriedade(array $v): string
    {
        $t = (string) ($v['type'] ?? '');
        $x = $v[$t] ?? null;

        return trim((string) match ($t) {
            'title', 'rich_text' => self::texto($x ?? []),
            'number'             => $x === null ? '' : (string) $x,
            'select', 'status'   => $x['name'] ?? '',
            'multi_select'       => implode(', ', array_map(fn ($o) => $o['name'] ?? '', (array) $x)),
            'date'               => $x ? trim(($x['start'] ?? '') . (! empty($x['end']) ? ' → ' . $x['end'] : '')) : '',
            'checkbox'           => $x ? '✔' : '',
            'url', 'email', 'phone_number', 'created_time', 'last_edited_time' => (string) $x,
            'people'             => implode(', ', array_map(fn ($o) => $o['name'] ?? '', (array) $x)),
            'relation'           => $x ? count((array) $x) . ' ligação(ões)' : '',
            'formula'            => is_array($x) ? (is_bool($x[$x['type'] ?? ''] ?? null) ? (($x[$x['type']] ?? false) ? '✔' : '') : (is_array($x[$x['type'] ?? ''] ?? null) ? ($x[$x['type']]['start'] ?? '') : (string) ($x[$x['type'] ?? ''] ?? ''))) : '',
            'rollup'             => is_array($x) && ($x['type'] ?? '') === 'number' ? (string) ($x['number'] ?? '') : '',
            'files'              => implode(', ', array_map(fn ($o) => $o['name'] ?? '', (array) $x)),
            'unique_id'          => is_array($x) ? trim(($x['prefix'] ?? '') . '-' . ($x['number'] ?? ''), '-') : '',
            default              => '',
        });
    }

    /* ---------------- texto rico ---------------- */

    public static function tituloPagina(array $p): string
    {
        foreach ((array) ($p['properties'] ?? []) as $v) {
            if (is_array($v) && ($v['type'] ?? '') === 'title') {
                return self::texto($v['title'] ?? []) ?: 'Sem título';
            }
        }

        return 'Sem título';
    }

    /** Texto simples de uma lista rich_text. */
    public static function texto(array $rt): string
    {
        return implode('', array_map(fn ($r) => (string) ($r['plain_text'] ?? ''), $rt));
    }

    /** rich_text em Markdown (negrito, itálico, tachado, código e links). */
    public static function rico(array $rt): string
    {
        $s = '';
        foreach ($rt as $r) {
            $t = (string) ($r['plain_text'] ?? '');
            if ($t === '') continue;
            $a = (array) ($r['annotations'] ?? []);
            // marcações não podem encostar em espaço: guarda os espaços das pontas fora delas
            preg_match('/^(\s*)(.*?)(\s*)$/s', $t, $m);
            [$ini, $meio, $fim] = [$m[1], $m[2], $m[3]];
            if ($meio !== '') {
                if (! empty($a['code'])) $meio = '`' . $meio . '`';
                if (! empty($a['bold'])) $meio = '**' . $meio . '**';
                if (! empty($a['italic'])) $meio = '*' . $meio . '*';
                if (! empty($a['strikethrough'])) $meio = '~~' . $meio . '~~';
                $href = (string) ($r['href'] ?? '');
                if ($href !== '' && ($r['type'] ?? '') !== 'mention') $meio = self::link($href, $meio);
            }
            $s .= $ini . $meio . $fim;
        }

        return $s;
    }

    private static function link(string $url, string $texto): string
    {
        if (! preg_match('#^https?://#i', $url)) return $texto;

        return '[' . $texto . '](' . str_replace([' ', ')'], ['%20', '%29'], $url) . ')';
    }

    private static function curto(mixed $v, int $max): ?string
    {
        return is_string($v) && $v !== '' ? mb_substr($v, 0, $max) : null;
    }
}
