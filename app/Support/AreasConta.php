<?php

namespace App\Support;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Áreas de cada conta: a ordem no menu de cima e no painel, as escondidas, os nomes trocados e as áreas
 * criadas pela pessoa. Guardado em users.areas_config (JSON):
 *   { ordem: [slug|id...], ocultas: [slug|id...], nomes: {slug: nome}, extras: [{id, nome}] }
 *
 * - As cinco áreas do sistema (Tarefa::AREAS) nunca são apagadas: "excluir" é esconder, e os dados ficam
 *   guardados (voltam ao mostrar a área de novo).
 * - Uma área criada ("extra", id x + 8 letras/números) é um quadro como o Diverso: as abas dela moram no
 *   estado do Diverso com "grupo" = id da área (diverso.js). Assim exportar, importar, compartilhar, fundos
 *   e o cartão Editor de PDF continuam valendo sem mudar nada (tudo é do Diverso).
 */
class AreasConta
{
    public const MAX_EXTRAS = 12;

    public const MAX_NOME = 30;

    public const REGEX_EXTRA = '/^x[a-z0-9]{8}$/';

    /** Ícone das áreas criadas (o mesmo do Diverso: quatro quadrados). */
    public const ICONE_EXTRA = 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z';

    /** Configuração normalizada (sem nada inválido) da conta. */
    public static function config(?User $u): array
    {
        $bruto = $u ? $u->getAttribute('areas_config') : null;
        $c = is_string($bruto) ? json_decode($bruto, true) : (is_array($bruto) ? $bruto : null);
        $c = is_array($c) ? $c : [];

        $extras = [];
        foreach (is_array($c['extras'] ?? null) ? $c['extras'] : [] as $x) {
            if (! is_array($x) || ! is_string($x['id'] ?? null) || ! preg_match(self::REGEX_EXTRA, $x['id'])) continue;
            if (isset($extras[$x['id']]) || count($extras) >= self::MAX_EXTRAS) continue;
            $extras[$x['id']] = ['id' => $x['id'], 'nome' => self::limparNome($x['nome'] ?? '') ?: 'Nova área'];
        }

        $validos = array_merge(array_keys(Tarefa::AREAS), array_keys($extras));
        $ordem = array_values(array_unique(array_filter(is_array($c['ordem'] ?? null) ? $c['ordem'] : [], fn ($s) => is_string($s) && in_array($s, $validos, true))));
        foreach ($validos as $s) {
            if (! in_array($s, $ordem, true)) $ordem[] = $s; // novas (ou nunca ordenadas) entram no fim
        }
        $ocultas = array_values(array_unique(array_filter(is_array($c['ocultas'] ?? null) ? $c['ocultas'] : [], fn ($s) => is_string($s) && in_array($s, $validos, true))));

        $nomes = [];
        foreach (is_array($c['nomes'] ?? null) ? $c['nomes'] : [] as $s => $n) {
            if (isset(Tarefa::AREAS[$s]) && ($n = self::limparNome($n)) !== '' && $n !== Tarefa::AREAS[$s]['nome']) $nomes[$s] = $n;
        }

        return ['ordem' => $ordem, 'ocultas' => $ocultas, 'nomes' => $nomes, 'extras' => array_values($extras)];
    }

    private static function limparNome($n): string
    {
        $n = is_string($n) ? trim(preg_replace('/\s+/u', ' ', strip_tags($n))) : '';

        return mb_substr($n, 0, self::MAX_NOME);
    }

    /**
     * Todas as áreas da conta, na ordem escolhida: [slug => [slug, nome, nome_padrao, descricao, icone, url, extra, oculta]].
     * $soVisiveis = sem as escondidas.
     */
    public static function lista(?User $u, bool $soVisiveis = false): array
    {
        $c = self::config($u);
        $extras = array_column($c['extras'], null, 'id');
        $saida = [];
        foreach ($c['ordem'] as $s) {
            $oculta = in_array($s, $c['ocultas'], true);
            if ($soVisiveis && $oculta) continue;
            if (isset(Tarefa::AREAS[$s])) {
                $a = Tarefa::AREAS[$s];
                $saida[$s] = [
                    'slug' => $s, 'nome' => $c['nomes'][$s] ?? $a['nome'], 'nome_padrao' => $a['nome'],
                    'descricao' => $a['descricao'], 'icone' => $a['icone'], 'url' => route('areas.show', $s),
                    'extra' => false, 'oculta' => $oculta,
                ];
            } elseif (isset($extras[$s])) {
                $saida[$s] = [
                    'slug' => $s, 'nome' => $extras[$s]['nome'], 'nome_padrao' => null,
                    'descricao' => 'Área criada por você: abas com quadros livres.', 'icone' => self::ICONE_EXTRA,
                    'url' => route('areas.extra', $s), 'extra' => true, 'oculta' => $oculta,
                ];
            }
        }

        return $saida;
    }

    /** Nome que a conta deu à área (ou o padrão). */
    public static function nome(?User $u, string $slug): string
    {
        $l = self::lista($u);

        return $l[$slug]['nome'] ?? (Tarefa::AREAS[$slug]['nome'] ?? $slug);
    }

    /** Área criada pela conta, ou null. */
    public static function extra(?User $u, string $id): ?array
    {
        foreach (self::config($u)['extras'] as $x) {
            if ($x['id'] === $id) return $x;
        }

        return null;
    }

    private static function gravar(User $u, array $c): void
    {
        $u->forceFill(['areas_config' => json_encode($c, JSON_UNESCAPED_UNICODE)])->save();
    }

    private static function falha(string $msg): ValidationException
    {
        return ValidationException::withMessages(['areas' => $msg]);
    }

    /** Ordem, escondidas e nomes vindos da tela "Áreas". */
    public static function salvar(User $u, array $entrada): void
    {
        $c = self::config($u);
        $validos = $c['ordem'];

        if (isset($entrada['ordem'])) {
            $ordem = array_values(array_unique(array_filter((array) $entrada['ordem'], fn ($s) => is_string($s) && in_array($s, $validos, true))));
            foreach ($validos as $s) if (! in_array($s, $ordem, true)) $ordem[] = $s;
            $c['ordem'] = $ordem;
        }
        if (isset($entrada['ocultas'])) {
            $ocultas = array_values(array_unique(array_filter((array) $entrada['ocultas'], fn ($s) => is_string($s) && in_array($s, $validos, true))));
            if (count($ocultas) >= count($validos)) throw self::falha('Deixe pelo menos uma área à mostra.');
            $c['ocultas'] = $ocultas;
        }
        if (isset($entrada['nomes']) && is_array($entrada['nomes'])) {
            foreach ($entrada['nomes'] as $s => $n) {
                $n = self::limparNome($n);
                if (isset(Tarefa::AREAS[$s])) {
                    if ($n === '' || $n === Tarefa::AREAS[$s]['nome']) unset($c['nomes'][$s]);
                    else $c['nomes'][$s] = $n;
                } else {
                    foreach ($c['extras'] as &$x) {
                        if ($x['id'] === $s && $n !== '') $x['nome'] = $n;
                    }
                    unset($x);
                }
            }
        }
        self::gravar($u, $c);
    }

    /** Cria uma área (quadro com abas, como o Diverso). Devolve a área criada. */
    public static function criarExtra(User $u, string $nome): array
    {
        $c = self::config($u);
        $nome = self::limparNome($nome);
        if ($nome === '') throw self::falha('Dê um nome à área.');
        if (count($c['extras']) >= self::MAX_EXTRAS) throw self::falha('Limite de ' . self::MAX_EXTRAS . ' áreas criadas. Exclua alguma para criar outra.');

        do {
            $id = 'x' . substr(str_shuffle(str_repeat('abcdefghijkmnpqrstuvwxyz23456789', 3)), 0, 8);
        } while (self::extra($u, $id));

        $x = ['id' => $id, 'nome' => $nome];
        $c['extras'][] = $x;
        $c['ordem'][] = $id;
        self::gravar($u, $c);

        return $x;
    }

    /** Tira a área criada da configuração (o que fazer com as abas dela fica com MoverCartoes::soltarGrupo). */
    public static function removerExtra(User $u, string $id): void
    {
        $c = self::config($u);
        $c['extras'] = array_values(array_filter($c['extras'], fn ($x) => $x['id'] !== $id));
        $c['ordem'] = array_values(array_filter($c['ordem'], fn ($s) => $s !== $id));
        $c['ocultas'] = array_values(array_filter($c['ocultas'], fn ($s) => $s !== $id));
        self::gravar($u, $c);
    }

    /** Esconde (ou mostra) uma área. */
    public static function esconder(User $u, string $slug, bool $sim = true): void
    {
        $c = self::config($u);
        $ocultas = array_values(array_filter($c['ocultas'], fn ($s) => $s !== $slug));
        if ($sim) $ocultas[] = $slug;
        self::salvar($u, ['ocultas' => $ocultas]);
    }
}
