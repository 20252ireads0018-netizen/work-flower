<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Quem do Work Flower também está cadastrado nos outros sites da Websight (config/websight.php).
 * Só leitura, um SELECT por site com os e-mails procurados (em lotes), guardado em cache por 10 minutos.
 */
class SitesWebsight
{
    private const CACHE_MIN = 10;

    /** [id => ['nome', 'url', 'estado' => 'ok'|'sem-config'|'erro', 'erro' => ?string]] */
    public static function sites(): array
    {
        $saida = [];
        foreach ((array) config('websight.sites', []) as $id => $s) {
            $saida[$id] = [
                'nome' => (string) ($s['nome'] ?? $id),
                'url' => $s['url'] ?? null,
                'estado' => empty($s['db']) ? 'sem-config' : 'ok',
                'erro' => null,
            ];
        }

        return $saida;
    }

    /**
     * Para cada site configurado, quais dos e-mails dados existem lá.
     *
     * @param  string[]  $emails
     * @return array{sites: array, achados: array<string, array<string, true>>}
     */
    public static function cadastros(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $emails))));
        $sites = self::sites();
        $achados = [];
        foreach ($sites as $id => &$s) {
            $achados[$id] = [];
            if ($s['estado'] !== 'ok' || ! $emails) continue;
            $chave = 'websight.' . $id . '.' . md5(implode(',', $emails));
            try {
                $lista = Cache::remember($chave, now()->addMinutes(self::CACHE_MIN), fn () => self::procurar($id, $emails));
                $achados[$id] = array_fill_keys($lista, true);
            } catch (\Throwable $e) {
                $s['estado'] = 'erro';
                $s['erro'] = self::mensagem($e);
            }
        }
        unset($s);

        return ['sites' => $sites, 'achados' => $achados];
    }

    /** Total de cadastros de cada site (para o resumo), ou null se não deu para contar. */
    public static function totais(): array
    {
        $t = [];
        foreach (self::sites() as $id => $s) {
            if ($s['estado'] !== 'ok') { $t[$id] = null; continue; }
            try {
                $t[$id] = Cache::remember('websight.' . $id . '.total', now()->addMinutes(self::CACHE_MIN), function () use ($id) {
                    $c = config("websight.sites.$id");
                    return (int) DB::connection(self::conexao($id))->table($c['tabela'])->whereNotNull($c['coluna'])->distinct()->count($c['coluna']);
                });
            } catch (\Throwable) {
                $t[$id] = null;
            }
        }

        return $t;
    }

    /** E-mails (minúsculos) da lista que existem no site. */
    private static function procurar(string $id, array $emails): array
    {
        $c = config("websight.sites.$id");
        $tabela = self::nome($c['tabela'] ?? 'users');
        $coluna = self::nome($c['coluna'] ?? 'email');
        $achou = [];
        foreach (array_chunk($emails, 300) as $lote) {
            $achou = array_merge($achou, DB::connection(self::conexao($id))->table($tabela)
                ->whereIn(DB::raw('LOWER(' . $coluna . ')'), $lote)
                ->pluck($coluna)->map(fn ($e) => mb_strtolower(trim((string) $e)))->all());
        }

        return array_values(array_unique($achou));
    }

    /** Registra (uma vez por pedido) a conexão só de leitura do site e devolve o nome dela. */
    private static function conexao(string $id): string
    {
        $nome = 'websight_' . $id;
        if (config("database.connections.$nome")) return $nome;
        $db = trim((string) config("websight.sites.$id.db"));
        if (preg_match('~^mysql://~i', $db)) {
            $cfg = ['driver' => 'mysql', 'url' => $db, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
                'options' => [\PDO::ATTR_TIMEOUT => 5]];
        } else {
            if (! is_file($db)) throw new \RuntimeException('Arquivo do banco não encontrado.');
            $cfg = ['driver' => 'sqlite', 'database' => $db, 'prefix' => '', 'foreign_key_constraints' => false];
        }
        config(["database.connections.$nome" => $cfg]);

        return $nome;
    }

    /** Nome de tabela/coluna da configuração: só letras, números e _. */
    private static function nome(string $n): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $n)) throw new \RuntimeException('Nome de tabela ou coluna inválido na configuração.');

        return $n;
    }

    /** Erro sem senha nem caminho completo. */
    private static function mensagem(\Throwable $e): string
    {
        $m = $e->getMessage();
        if (str_contains($m, 'not found') || str_contains($m, 'não encontrado')) return 'Banco não encontrado.';
        if (stripos($m, 'access denied') !== false) return 'Acesso negado pelo banco (usuário ou senha).';
        if (stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false) return 'A tabela de cadastro não existe nesse banco.';

        return 'Não consegui ler o banco desse site.';
    }
}
