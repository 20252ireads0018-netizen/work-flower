<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * WAF simples nas rotas que gravam (POST/PUT/PATCH/DELETE): recusa com 403 pedidos com assinaturas óbvias de
 * injeção de SQL, XSS, travessia de pasta e afins. É uma camada a mais: as consultas já usam parâmetros e o Blade
 * escapa a saída. Os padrões são estreitos de propósito (texto comum em português não deve cair aqui).
 *
 * Não confere o conteúdo livre que os cartões guardam como dado e só mostram escapado: o JSON dos quadros
 * ("valor" em /{area}/dados, inclusive os pedaços base64 do cartão Banco de dados), o código do cartão IDE na
 * Biblioteca, o arquivo de importação de workflows, as operações do tabletop e as ações das automações.
 * Senhas e códigos também ficam de fora. Webhooks (assinados pelo serviço) e a API do agente também.
 */
class FiltroAtaques
{
    /** Campos nunca conferidos, em qualquer rota e em qualquer nível. */
    private const CAMPOS_LIVRES = ['password', 'password_confirmation', 'senha', 'senha_atual', '_token', '_method'];

    /** Rotas (padrão do caminho) => campos de conteúdo livre (o nome vale em qualquer nível). */
    private const ISENTOS = [
        '*/dados/*'               => ['valor'],
        'biblioteca'              => ['dados', 'imagem'],
        'workflows/importar'      => ['workflows'],
        'tabletop-vivo'           => ['dados'],
        'tabletop-vivo/*'         => ['ops', 'op', 'dados', 'valor', 'img'],
        'automacoes'              => ['acoes', 'acoes_b64'],
        'automacoes/*'            => ['acoes', 'acoes_b64'],
        'editor-pdf/*'            => ['parte', 'dados'],
        'midia/*'                 => ['dados'],
    ];

    /** Rotas que não passam pelo filtro (corpo assinado ou binário). */
    private const ROTAS_FORA = ['whatsapp/webhook', 'loja/*/mp/aviso', 'api/*', 'webhooks/*', 'midia/*/*'];

    /** Assinaturas. Todas sem diferenciar maiúsculas. */
    private const PADROES = [
        'xss.script'     => '~<\s*/?\s*script\b~i',
        'xss.tag'        => '~<\s*(?:iframe|frame|frameset|object|embed|applet|base|svg|math)\b~i',
        'xss.evento'     => '~<[a-z][^>]*[\s/"\']on[a-z]{3,}\s*=~i',
        // no começo do valor só sem espaço depois dos dois pontos ("JavaScript: estudar" é título de tarefa, não ataque)
        'xss.url'        => '~^\s*(?:javascript|vbscript)\s*:(?!\s)|(?:href|src|action|formaction|xlink:href)\s*=\s*["\']?\s*(?:javascript|vbscript)\s*:|\bdata\s*:\s*text/html\b~i',
        'sql.union'      => '~\bunion\b(?:\s|/\*.*?\*/|\+)+(?:all\s+|distinct\s+)?select\b~i',
        'sql.tautologia' => '~[\'"`]\s*\)?\s*(?:or|and|\|\||&&)\s+[\'"`]?(\w+)[\'"`]?\s*=\s*[\'"`]?\1\b~i',
        'sql.empilhada'  => '~[\'"`]\s*\)?\s*;\s*(?:drop|truncate|alter|delete|insert|update|create|exec|shutdown)\b~i',
        'sql.drop'       => '~;\s*drop\s+(?:table|database|schema)\b~i',
        'sql.tempo'      => '~\b(?:sleep|pg_sleep)\s*\(\s*\d+(?:\.\d+)?\s*\)|\bbenchmark\s*\(\s*\d+\s*,|\bwaitfor\s+delay\s+[\'"]~i',
        'sql.esquema'    => '~\binformation_schema\b|\bmysql\.user\b|\bsqlite_master\b|\bpg_catalog\b~i',
        'sql.arquivo'    => '~\bload_file\s*\(|\binto\s+(?:out|dump)file\b~i',
        'sql.comentario' => '~[\'"`]\s*\)?\s*(?:--|#)\s*$~',
        'caminho'        => '~(?:\.\.[/\\\\]){2,}|(?:%2e%2e(?:%2f|%5c)){2,}~i',
        'nulo'           => '~\x00~',
        'php'            => '~<\?(?:php|=)~i',
        'jndi'           => '~\$\{\s*jndi\s*:~i',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || $this->fora($request)) {
            return $next($request);
        }

        $livres = array_merge(self::CAMPOS_LIVRES, $this->isentos($request));
        $achado = $this->procurarNaQuery($request) ?? $this->procurar($request->except(array_keys($request->allFiles())), $livres, '');

        if ($achado !== null) {
            [$campo, $regra] = $achado;
            Log::warning('Pedido recusado pelo filtro de ataques', [
                'regra' => $regra, 'campo' => $campo, 'caminho' => $request->path(),
                'ip' => $request->ip(), 'user_id' => $request->user()?->id,
            ]);
            $msg = 'Pedido recusado: o conteúdo enviado parece um ataque (código de script ou de banco de dados). Se foi engano, tire esse trecho ou fale com o suporte.';

            return $request->expectsJson()
                ? response()->json(['message' => $msg, 'regra' => $regra], 403)
                : response($msg, 403)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        return $next($request);
    }

    /** Nome da regra que o texto dispara, ou null. */
    public static function examinar(string $valor): ?string
    {
        // base64 / data URL puros não têm < ' " ; espaço: pula a varredura (imagens grandes)
        if (strlen($valor) > 256 && preg_match('~^(?:data:[\w/+.-]+;base64,)?[A-Za-z0-9+/=\r\n]+$~', $valor)) {
            return null;
        }
        foreach (self::PADROES as $nome => $re) {
            if (preg_match($re, $valor)) {
                return $nome;
            }
        }

        return null;
    }

    private function procurar(mixed $dados, array $livres, string $prefixo, int $nivel = 0): ?array
    {
        if ($nivel > 32 || ! is_array($dados)) {
            return null;
        }
        foreach ($dados as $chave => $valor) {
            $nome = (string) $chave;
            if (in_array($nome, $livres, true)) {
                continue;
            }
            $caminho = $prefixo === '' ? $nome : $prefixo . '.' . $nome;
            if (! is_int($chave) && ($r = self::examinar($nome))) {
                return [$caminho, $r];
            }
            if (is_string($valor)) {
                if ($r = self::examinar($valor)) {
                    return [$caminho, $r];
                }
            } elseif (is_array($valor) && ($achado = $this->procurar($valor, $livres, $caminho, $nivel + 1))) {
                return $achado;
            }
        }

        return null;
    }

    private function procurarNaQuery(Request $request): ?array
    {
        $q = (string) $request->server('QUERY_STRING');
        if ($q === '') {
            return null;
        }
        $texto = urldecode($q);

        return ($r = self::examinar($texto)) ? ['?query', $r] : null;
    }

    private function fora(Request $request): bool
    {
        foreach (self::ROTAS_FORA as $p) {
            if ($request->is($p)) {
                return true;
            }
        }

        return false;
    }

    private function isentos(Request $request): array
    {
        $campos = [];
        foreach (self::ISENTOS as $padrao => $lista) {
            if ($request->is($padrao)) {
                $campos = array_merge($campos, $lista);
            }
        }

        return $campos;
    }
}
