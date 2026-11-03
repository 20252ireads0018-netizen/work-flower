<?php

namespace App\Support;

use App\Models\CorpoDado;
use Illuminate\Support\Facades\Storage;

/**
 * Ambiente de cada workflow (Corpo, Mente, Carteira, Diverso) e de cada setor dele (as abas: Dietas, Livros,
 * as abas criadas no Diverso…): fundo próprio e música. O setor sem ambiente próprio usa o da área, e a área
 * sem fundo próprio usa o fundo geral da conta.
 * Fica nos dados do DONO do workflow (quem foi convidado vê o mesmo ambiente).
 *   corpo_dados "amb.{area}"        = opções da área (JSON: fundo e música)
 *   corpo_dados "amb.{area}.s.{h}"  = opções de um setor (h = sha1 do id da aba, para caber nos 60 da chave)
 *   corpo_dados "{chave acima}.img" = imagem ou GIF do fundo (data URL)
 *   storage/app/private/ambiente/{dono}/{area}[.s.{h}].{ext} = arquivo de áudio
 */
class Ambiente
{
    public const AREAS = ['corpo', 'mente', 'carteira', 'diverso'];

    public const AUDIO_EXT = ['mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac', 'webm'];

    /** Id de aba aceito como setor (o mesmo formato das abas do Diverso). */
    public const SETOR_RE = '/^[A-Za-z0-9._-]{1,60}$/';

    public const PADRAO = [
        'fundo'  => ['proprio' => false, 'cor' => null, 'ajuste' => 'cover', 'escurecer' => 55, 'desfoque' => 0],
        'musica' => ['tipo' => 'nenhuma', 'youtube' => null, 'nome' => null, 'volume' => 40, 'auto' => true],
    ];

    /** Id do setor conferido, ou null (vazio ou fora do formato = a área inteira). */
    public static function setor(mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor !== '' && preg_match(self::SETOR_RE, $valor) ? $valor : null;
    }

    /** Parte da chave e do nome do áudio que identifica a área ou o setor. */
    public static function base(string $area, ?string $setor = null): string
    {
        return $setor === null ? $area : "{$area}.s." . substr(sha1($setor), 0, 16);
    }

    public static function chave(string $area, ?string $setor = null): string
    {
        return 'amb.' . self::base($area, $setor);
    }

    /** Opções gravadas, sempre completas e conferidas (o JSON pode ter sido salvo por versões antigas). */
    public static function opcoes(int $donoId, string $area, ?string $setor = null): array
    {
        $salvo = json_decode((string) CorpoDado::where('user_id', $donoId)->where('chave', self::chave($area, $setor))->value('valor'), true);

        return self::conferir(is_array($salvo) ? $salvo : [], $setor !== null);
    }

    private static function conferir(array $salvo, bool $eSetor): array
    {
        $o = self::PADRAO;
        $f = $salvo['fundo'] ?? [];
        $o['fundo']['proprio'] = (bool) ($f['proprio'] ?? false);
        $o['fundo']['cor'] = isset($f['cor']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $f['cor']) ? $f['cor'] : null;
        $o['fundo']['ajuste'] = in_array($f['ajuste'] ?? '', ['cover', 'contain', 'repeat'], true) ? $f['ajuste'] : 'cover';
        $o['fundo']['escurecer'] = max(0, min(90, (int) ($f['escurecer'] ?? 55)));
        $o['fundo']['desfoque'] = max(0, min(20, (int) ($f['desfoque'] ?? 0)));

        // "herdar" só existe no setor: a aba toca a mesma música da área (é o padrão de uma aba nova)
        $m = $salvo['musica'] ?? [];
        $tipos = $eSetor ? ['herdar', 'nenhuma', 'arquivo', 'youtube'] : ['nenhuma', 'arquivo', 'youtube'];
        $o['musica']['tipo'] = in_array($m['tipo'] ?? '', $tipos, true) ? $m['tipo'] : ($eSetor ? 'herdar' : 'nenhuma');
        $o['musica']['youtube'] = self::idYoutube((string) ($m['youtube'] ?? ''));
        $o['musica']['nome'] = isset($m['nome']) ? mb_substr((string) $m['nome'], 0, 120) : null;
        $o['musica']['volume'] = max(0, min(100, (int) ($m['volume'] ?? 40)));
        $o['musica']['auto'] = (bool) ($m['auto'] ?? true);

        return $o;
    }

    /** Grava as opções (o setor guarda o próprio id junto, para a página achar o ambiente de cada aba). */
    public static function gravar(int $donoId, string $area, ?string $setor, array $o): void
    {
        unset($o['setor']);
        if ($setor !== null) {
            $o['setor'] = $setor;
        }
        CorpoDado::updateOrCreate(['user_id' => $donoId, 'chave' => self::chave($area, $setor)], ['valor' => json_encode($o)]);
    }

    /** Setor que voltou a usar tudo da área: apaga o registro dele. */
    public static function limparSeHerda(int $donoId, string $area, ?string $setor): void
    {
        if ($setor === null) {
            return;
        }
        $o = self::opcoes($donoId, $area, $setor);
        $temImagem = CorpoDado::where('user_id', $donoId)->where('chave', self::chave($area, $setor) . '.img')->exists();
        if (! $o['fundo']['proprio'] && $o['musica']['tipo'] === 'herdar' && ! $temImagem && ! self::caminhoAudio($donoId, $area, $setor)) {
            CorpoDado::where('user_id', $donoId)->where('chave', self::chave($area, $setor))->delete();
        }
    }

    /** Tudo o que a página precisa para montar o fundo e o tocador. */
    public static function paraPagina(int $donoId, string $area, ?string $setor = null): array
    {
        $o = self::opcoes($donoId, $area, $setor);
        $img = CorpoDado::where('user_id', $donoId)->where('chave', self::chave($area, $setor) . '.img')->first(['id', 'updated_at']);
        $o['fundo']['imagem'] = (bool) $img;
        $o['fundo']['versao'] = $img?->updated_at?->timestamp ?? 0;
        $audio = self::caminhoAudio($donoId, $area, $setor);
        $o['musica']['arquivo'] = (bool) $audio;
        $o['musica']['versao'] = $audio ? Storage::disk('local')->lastModified($audio) : 0;

        return $o;
    }

    /** Ambiente de cada setor que tem algo próprio, pelo id da aba. */
    public static function setoresDaPagina(int $donoId, string $area): array
    {
        $saida = [];
        $linhas = CorpoDado::where('user_id', $donoId)->where('chave', 'like', "amb.{$area}.s.%")
            ->where('chave', 'not like', '%.img')->limit(200)->get(['valor']);
        foreach ($linhas as $l) {
            $setor = self::setor(json_decode((string) $l->valor, true)['setor'] ?? null);
            if ($setor !== null) {
                $saida[$setor] = self::paraPagina($donoId, $area, $setor);
            }
        }

        return $saida;
    }

    /** Caminho do áudio gravado (ou null). */
    public static function caminhoAudio(int $donoId, string $area, ?string $setor = null): ?string
    {
        foreach (self::AUDIO_EXT as $ext) {
            $p = "ambiente/{$donoId}/" . self::base($area, $setor) . ".{$ext}";
            if (Storage::disk('local')->exists($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Id do vídeo (11 caracteres) a partir de um link do YouTube ou do próprio id; null se não for válido.
     * Aceita youtube.com/watch?v=, youtu.be/, /shorts/, /embed/, /live/ e music.youtube.com.
     */
    public static function idYoutube(string $texto): ?string
    {
        $texto = trim($texto);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $texto)) {
            return $texto;
        }
        $u = parse_url($texto);
        $host = strtolower($u['host'] ?? '');
        if (! preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be)$/', $host)) {
            return null;
        }
        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{11})#', $u['path'] ?? '', $m)) {
            return $m[1];
        }
        parse_str($u['query'] ?? '', $q);
        if (isset($q['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $q['v'])) {
            return $q['v'];
        }
        if (preg_match('#^/(?:shorts|embed|live|v)/([A-Za-z0-9_-]{11})#', $u['path'] ?? '', $m)) {
            return $m[1];
        }

        return null;
    }
}
