<?php

namespace App\Support;

/**
 * Abas que saíram das áreas (09/11/2026): "Mapa mental" (Corpo, Mente e Carteira) e "Trabalho" (Carteira).
 * Nada se perde: os cartões dessas abas vão para o "Quadro livre" da mesma área, logo abaixo do que já havia lá,
 * mantendo a posição relativa, o tamanho, a cor e o fundo de cada um (as imagens img.<id> não mudam de chave).
 * O cartão fixo "Projetos" da aba Trabalho vai junto quando há projetos (carteira.js o mostra no Quadro livre).
 * O mapa mental continua existindo como tipo de cartão.
 *
 * O estado é lido como objetos (stdClass), não como arrays: assim {} continua {} ao gravar de novo
 * (com arrays, um objeto vazio viraria [] e o JS perderia o que pusesse nele).
 */
class AbasRetiradas
{
    public const RETIRADAS = [
        'corpo'    => ['mapa'],
        'mente'    => ['mapa'],
        'carteira' => ['trabalho', 'mapa'],
    ];

    public const DESTINO = 'livre';

    private const FOLGA = 16;

    /** Abas retiradas de uma área, na ordem em que os cartões entram no Quadro livre. */
    public static function abas(string $area): array
    {
        return self::RETIRADAS[$area] ?? [];
    }

    /** Decodifica o estado guardado (JSON) como objetos; null se não for um objeto. */
    public static function decodificar(?string $json): ?object
    {
        if ($json === null || $json === '') {
            return null;
        }
        $e = json_decode($json, false, 512, JSON_BIGINT_AS_STRING);

        return is_object($e) ? $e : null;
    }

    public static function codificar(object $e): string
    {
        return json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Fundo (maior y + h) dos retângulos de um layout de aba. */
    public static function fundo(object $lay, ?array $soIds = null): float
    {
        $max = 0;
        foreach (get_object_vars($lay) as $id => $r) {
            if ($soIds !== null && ! in_array((string) $id, $soIds, true)) continue;
            if (is_object($r) && is_numeric($r->y ?? null) && is_numeric($r->h ?? null)) {
                $max = max($max, (float) $r->y + (float) $r->h);
            }
        }

        return $max;
    }

    /** Muda o estado no lugar. Devolve true se algo mudou. */
    public static function migrar(object $e, string $area): bool
    {
        $secs = self::abas($area);
        if (! $secs) return false;

        $layout = isset($e->layout) && is_object($e->layout) ? $e->layout : new \stdClass;
        $blocos = isset($e->blocos) && is_array($e->blocos) ? $e->blocos : [];

        $tem = false;
        foreach ($secs as $s) {
            if (property_exists($layout, $s)) $tem = true;
        }
        foreach ($blocos as $b) {
            if (is_object($b) && in_array($b->secao ?? null, $secs, true)) $tem = true;
        }
        if (! $tem) return false;

        $dest = isset($layout->{self::DESTINO}) && is_object($layout->{self::DESTINO}) ? $layout->{self::DESTINO} : new \stdClass;
        // só conta o que existe de fato no Quadro livre (layout de blocos apagados é ignorado)
        $idsLivre = [];
        foreach ($blocos as $b) {
            if (is_object($b) && ($b->secao ?? null) === self::DESTINO && isset($b->id)) $idsLivre[] = (string) $b->id;
        }
        $y0 = self::fundo($dest, $idsLivre);

        foreach ($secs as $s) {
            $lay = isset($layout->$s) && is_object($layout->$s) ? $layout->$s : new \stdClass;
            $ids = [];
            foreach ($blocos as $b) {
                if (is_object($b) && ($b->secao ?? null) === $s && isset($b->id)) $ids[] = (string) $b->id;
            }
            // cartão fixo "Projetos" (Carteira › Trabalho): só vai se houver projetos
            $fixos = [];
            if ($area === 'carteira' && $s === 'trabalho' && ! empty($e->projetos) && is_array($e->projetos)) {
                $fixos['projetos'] = (object) ['x' => 0, 'y' => 0, 'w' => 1088, 'h' => 440, 'z' => 1];
            }

            $rects = [];
            $alt = 0;
            foreach (array_merge(array_keys($fixos), $ids) as $id) {
                $r = isset($lay->$id) && is_object($lay->$id) ? clone $lay->$id : clone ($fixos[$id] ?? (object) ['x' => 0, 'y' => $alt, 'w' => 360, 'h' => 260, 'z' => 1]);
                $r->y = is_numeric($r->y ?? null) ? (float) $r->y : 0;
                $r->h = is_numeric($r->h ?? null) ? (float) $r->h : 260;
                $rects[$id] = $r;
                $alt = max($alt, $r->y + $r->h);
            }
            if ($rects) {
                $topo = min(array_map(fn ($r) => $r->y, $rects));
                $inicio = $y0 > 0 ? $y0 + self::FOLGA : 0;
                foreach ($rects as $id => $r) {
                    $r->y = self::inteiro($r->y - $topo + $inicio);
                    $r->h = self::inteiro($r->h);
                    $dest->$id = $r;
                    $idsLivre[] = (string) $id;
                }
                $y0 = self::fundo($dest, $idsLivre);
            }

            foreach ($blocos as $b) {
                if (is_object($b) && ($b->secao ?? null) === $s) $b->secao = self::DESTINO;
            }
            unset($layout->$s);
        }

        $layout->{self::DESTINO} = $dest;
        $e->layout = $layout;
        $e->blocos = $blocos;

        return true;
    }

    /** Número sem casas quando dá (o layout do JS usa inteiros). */
    private static function inteiro(float $v): float|int
    {
        return floor($v) == $v ? (int) $v : $v;
    }

    /**
     * Confere e, se preciso, migra o estado guardado da área do dono. Chamado ao abrir a página (antes de ler
     * os dados) e pela migração do banco. Devolve true se gravou.
     */
    public static function migrarConta(string $area, int $donoId): bool
    {
        if (! self::abas($area)) return false;
        [$classe, $prefixo] = Sincronia::armazenamento($area);
        $linha = $classe::where('user_id', $donoId)->where('chave', $prefixo . 'estado')->first();
        if (! $linha || ! is_string($linha->valor)) return false;

        return self::migrarLinha($linha, $area);
    }

    /** Migra uma linha (modelo com "valor") já lida. */
    public static function migrarLinha($linha, string $area): bool
    {
        // atalho: sem o nome de nenhuma aba retirada no texto, não há o que mudar (não decodifica o JSON inteiro)
        $achou = false;
        foreach (self::abas($area) as $s) {
            if (str_contains($linha->valor, '"' . $s . '"')) { $achou = true; break; }
        }
        if (! $achou) return false;

        $e = self::decodificar($linha->valor);
        if (! $e || ! self::migrar($e, $area)) return false;

        $linha->valor = self::codificar($e);
        $linha->save();

        return true;
    }
}
