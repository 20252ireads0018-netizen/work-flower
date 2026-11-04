<?php

namespace App\Support\Armazenamento;

use App\Models\ArmazenamentoArquivo;
use App\Models\ArmazenamentoDestino;
use App\Support\Compartilhar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tira do site os arquivos grandes e guarda no destino da conta:
 *   - imagens img.* (data URL em JSON) acima de config('armazenamento.imagem_grande'): a linha passa a guardar
 *     "/armazenamento/arquivo/{token}", que o navegador usa como src normalmente;
 *   - PDFs da Mente (storage/app/mente/pdf/{usuario}): o arquivo local sai e /pdf/{id} busca no destino.
 * "Trazer de volta" desfaz tudo. Cada chamada trabalha num lote, para caber no tempo de uma requisição.
 */
class Arquivos
{
    public const LOTE = 25;

    /** Bytes por clique: um lote para de pegar arquivos depois disto, para a requisição não passar do tempo. */
    public const MAX_BYTES_LOTE = 60 * 1024 * 1024;

    /** Leva um lote de arquivos para o destino. Devolve [movidos, bytes, restantes]. */
    public static function mover(ArmazenamentoDestino $destino, int $lote = self::LOTE): array
    {
        $uid = (int) $destino->user_id;
        $cliente = $destino->cliente();
        $movidos = 0;
        $bytes = 0;

        foreach (self::imagensGrandes($uid) as [$tabela, $id]) {
            if ($movidos >= $lote || $bytes >= self::MAX_BYTES_LOTE) break;
            $linha = DB::table($tabela)->where('id', $id)->first(['id', 'chave', 'valor', 'updated_at']);
            $img = $linha ? self::imagemDe($linha->valor) : null;
            if (! $img) continue;

            [$mime, $binario] = $img;
            $token = Str::random(32);
            $remoto = $cliente->enviar('arquivos/' . $token . '.' . self::extensao($mime), $binario, $mime);

            // só troca se ninguém mexeu na imagem enquanto ela subia
            $trocou = DB::table($tabela)->where('id', $linha->id)->where('updated_at', $linha->updated_at)
                ->update(['valor' => ArmazenamentoArquivo::valor($token), 'updated_at' => now()]);
            if (! $trocou) {
                $cliente->apagar($remoto);
                continue;
            }

            ArmazenamentoArquivo::create([
                'user_id' => $uid, 'destino_id' => $destino->id, 'token' => $token, 'tipo' => 'img',
                'tabela' => $tabela, 'chave' => $linha->chave, 'remoto' => $remoto, 'mime' => $mime, 'tamanho' => strlen($binario),
            ]);
            $movidos++;
            $bytes += strlen($binario);
        }

        $disco = Storage::disk('local');
        foreach (self::pdfsLocais($uid) as $caminho) {
            if ($movidos >= $lote || $bytes >= self::MAX_BYTES_LOTE) break;
            $id = pathinfo($caminho, PATHINFO_FILENAME);
            $binario = $disco->get($caminho);
            $token = Str::random(32);
            $remoto = $cliente->enviar('arquivos/' . $token . '.pdf', $binario, 'application/pdf');

            ArmazenamentoArquivo::where(['user_id' => $uid, 'tabela' => 'mente_pdf', 'chave' => $id])->get()
                ->each(fn ($velho) => self::esquecer($velho)); // PDF trocado depois de movido: a cópia velha sai
            ArmazenamentoArquivo::create([
                'user_id' => $uid, 'destino_id' => $destino->id, 'token' => $token, 'tipo' => 'pdf',
                'tabela' => 'mente_pdf', 'chave' => $id, 'remoto' => $remoto, 'mime' => 'application/pdf', 'tamanho' => strlen($binario),
            ]);
            $disco->delete($caminho);
            $movidos++;
            $bytes += strlen($binario);
        }

        return [$movidos, $bytes, self::pendentes($uid)];
    }

    /** Quantos arquivos ainda podem sair do site. */
    public static function pendentes(int $uid): int
    {
        return count(self::imagensGrandes($uid)) + count(self::pdfsLocais($uid));
    }

    /** Traz um lote de volta para o site e apaga do destino. Devolve [trazidos, restantes]. */
    public static function trazer(int $uid, int $lote = self::LOTE): array
    {
        $feitos = 0;
        $bytes = 0;
        foreach (ArmazenamentoArquivo::where('user_id', $uid)->with('destino')->orderBy('id')->take($lote)->get() as $a) {
            if ($bytes >= self::MAX_BYTES_LOTE) break;
            $bytes += (int) $a->tamanho;
            $binario = $a->destino->cliente()->baixar($a->remoto);

            if ($a->tipo === 'pdf') {
                $caminho = 'mente/pdf/' . $uid . '/' . $a->chave . '.pdf';
                if (! Storage::disk('local')->exists($caminho)) Storage::disk('local')->put($caminho, $binario);
            } else {
                DB::table($a->tabela)->where('user_id', $uid)->where('chave', $a->chave)
                    ->where('valor', ArmazenamentoArquivo::valor($a->token))
                    ->update(['valor' => json_encode('data:' . $a->mime . ';base64,' . base64_encode($binario), JSON_UNESCAPED_SLASHES), 'updated_at' => now()]);
            }

            self::esquecer($a);
            $feitos++;
        }

        return [$feitos, ArmazenamentoArquivo::where('user_id', $uid)->count()];
    }

    /**
     * Apaga do destino o que o site não usa mais: imagem trocada ou apagada no quadro, PDF enviado de novo.
     * Roda no backup diário.
     */
    public static function limparOrfaos(int $uid): int
    {
        $n = 0;
        foreach (ArmazenamentoArquivo::where('user_id', $uid)->with('destino')->get() as $a) {
            $usado = $a->tipo === 'pdf'
                ? ! Storage::disk('local')->exists('mente/pdf/' . $uid . '/' . $a->chave . '.pdf')
                : DB::table($a->tabela)->where('user_id', $uid)->where('chave', $a->chave)
                    ->where('valor', ArmazenamentoArquivo::valor($a->token))->exists();
            if (! $usado) {
                self::esquecer($a);
                $n++;
            }
        }

        return $n;
    }

    /** O PDF {id} saiu da Mente: a cópia no destino também sai. */
    public static function esquecerPdf(int $uid, string $id): void
    {
        ArmazenamentoArquivo::where(['user_id' => $uid, 'tabela' => 'mente_pdf', 'chave' => $id])->with('destino')->get()
            ->each(fn ($a) => self::esquecer($a));
    }

    public static function pdfMovido(int $uid, string $id): ?ArmazenamentoArquivo
    {
        return ArmazenamentoArquivo::where(['user_id' => $uid, 'tabela' => 'mente_pdf', 'chave' => $id])->latest('id')->first();
    }

    /** Quem está logado pode ver este arquivo? O dono, ou quem tem acesso ao workflow da área dele. */
    public static function podeVer(ArmazenamentoArquivo $a, int $usuarioId): bool
    {
        if ($usuarioId === (int) $a->user_id) return true;

        $area = match ($a->tabela) {
            'carteira_dados' => 'carteira',
            'negocio_dados'  => 'negocios',
            'diverso_dados'  => 'diverso',
            'mente_pdf'      => 'mente',
            default          => str_starts_with($a->chave, 'mente.') ? 'mente' : 'corpo',
        };

        return Compartilhar::podeAcessar($usuarioId, (int) $a->user_id, $area);
    }

    // ---------------------------------------------------------------- interno

    private static function esquecer(ArmazenamentoArquivo $a): void
    {
        try {
            $a->destino?->cliente()->apagar($a->remoto);
        } catch (RuntimeException $e) {
            report($e); // fica um arquivo sobrando no destino da pessoa; o registro sai mesmo assim
        }
        $a->delete();
    }

    /** [tabela, id] das linhas img.* grandes do usuário. */
    private static function imagensGrandes(int $uid): array
    {
        $min = (int) config('armazenamento.imagem_grande', 100 * 1024);
        $lista = [];
        foreach (array_keys(Uso::TABELAS) as $tabela) {
            $ids = DB::table($tabela)->where('user_id', $uid)
                ->where(fn ($q) => $q->where('chave', 'like', 'img.%')->orWhere('chave', 'like', 'mente.img.%'))
                ->where('valor', 'like', '"data:image%') // o JS grava sem escapar a barra; json_encode do PHP escapa
                ->whereRaw('LENGTH(valor) > ?', [$min])
                ->orderBy('id')->pluck('id');
            foreach ($ids as $id) $lista[] = [$tabela, $id];
        }

        return $lista;
    }

    /** [mime, binário] se o valor é uma imagem em data URL base64 (como o quadro guarda); senão null. */
    private static function imagemDe($valor): ?array
    {
        $src = json_decode((string) $valor, true);
        if (! is_string($src) || ! preg_match('#^data:(image/[a-z0-9.+-]+);base64,#i', $src, $m)) return null;
        $bin = base64_decode(substr($src, strlen($m[0])), true);

        return $bin === false || $bin === '' ? null : [strtolower($m[1]), $bin];
    }

    private static function extensao(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg',
            default => 'bin',
        };
    }

    private static function pdfsLocais(int $uid): array
    {
        try {
            return array_values(array_filter(Storage::disk('local')->files('mente/pdf/' . $uid), fn ($f) => str_ends_with(strtolower($f), '.pdf')));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
