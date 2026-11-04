<?php

namespace App\Http\Controllers;

use App\Support\Compartilhar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Arquivos do cartão "Editor de PDF" (js/editor-pdf.js), guardados em disco e não no banco.
 *
 * - Ficam em storage/app/editorpdf/{dono}/{area}/{arquivo}.pdf, sempre do DONO do workflow
 *   (Compartilhar::dono confere o acesso; quem só pode ver recebe 403 em qualquer gravação).
 * - O envio é em partes de até 3 MB (cada pedido leva o pedaço em base64 dentro de um JSON, ~4 MB):
 *   o mod_security da hospedagem recusa (406) corpos grandes ou que pareçam código, e base64 passa.
 *   POST .../parte?envio=X&n=0..  grava o pedaço n; POST .../concluir junta os pedaços, confere se é PDF
 *   e só então troca o arquivo (o PDF anterior continua valendo até o fim do envio).
 * - GET devolve o arquivo com suporte a Range (o pdf.js lê só as páginas que mostra).
 */
class EditorPdfController extends Controller
{
    private const AREAS = ['corpo', 'mente', 'carteira', 'negocios', 'diverso'];

    /** Tamanho máximo de um PDF (o editor aceita até 80 MB). */
    public const MAX_BYTES = 80 * 1024 * 1024;

    /** Tamanho máximo de cada pedaço já decodificado. */
    public const MAX_PARTE = 3 * 1024 * 1024 + 1024;

    /** Limites por conta (dono): quantidade de PDFs e espaço somado. */
    public const MAX_ARQUIVOS = 300;
    public const MAX_TOTAL = 1024 * 1024 * 1024; // 1 GB

    public function parte(Request $request, string $area, string $arquivo): JsonResponse
    {
        $dono = $this->dono($request, $area);
        $dados = $request->validate([
            'envio' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'n'     => ['required', 'integer', 'min:0', 'max:' . (int) ceil(self::MAX_BYTES / (self::MAX_PARTE - 1024))],
            'dados' => ['required', 'string', 'max:' . (int) ceil(self::MAX_PARTE * 4 / 3 + 8)],
        ]);
        $bin = base64_decode($dados['dados'], true);
        if ($bin === false || $bin === '' || strlen($bin) > self::MAX_PARTE) {
            return response()->json(['message' => 'Pedaço do arquivo inválido.'], 422);
        }
        if ((int) $dados['n'] === 0 && ! str_starts_with($bin, '%PDF')) {
            return response()->json(['message' => 'O arquivo não é um PDF.'], 422);
        }
        $disco = Storage::disk('local');
        $this->limparEnviosVelhos($dono, $area);
        $disco->put($this->pastaEnvio($dono, $area, $arquivo, $dados['envio']) . '/' . (int) $dados['n'] . '.parte', $bin);

        return response()->json(['ok' => true]);
    }

    public function concluir(Request $request, string $area, string $arquivo): JsonResponse
    {
        $dono = $this->dono($request, $area);
        $dados = $request->validate([
            'envio'   => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'partes'  => ['required', 'integer', 'min:1', 'max:200'],
            'tamanho' => ['required', 'integer', 'min:5', 'max:' . self::MAX_BYTES],
        ]);
        $disco = Storage::disk('local');
        $pasta = $this->pastaEnvio($dono, $area, $arquivo, $dados['envio']);
        $destino = $this->caminho($dono, $area, $arquivo);
        $soma = 0;
        for ($i = 0; $i < $dados['partes']; $i++) {
            if (! $disco->exists("$pasta/$i.parte")) {
                return response()->json(['message' => 'Faltou um pedaço do arquivo. Envie de novo.'], 422);
            }
            $soma += $disco->size("$pasta/$i.parte");
        }
        if ($soma !== (int) $dados['tamanho']) {
            $disco->deleteDirectory($pasta);
            return response()->json(['message' => 'O arquivo chegou incompleto. Envie de novo.'], 422);
        }
        // limites da conta (trocar o PDF de um cartão que já tem não conta como arquivo novo)
        [$qtd, $total] = $this->uso($dono);
        $anterior = $disco->exists($destino) ? $disco->size($destino) : null;
        if (($anterior === null && $qtd >= self::MAX_ARQUIVOS) || $total - ($anterior ?? 0) + $soma > self::MAX_TOTAL) {
            $disco->deleteDirectory($pasta);
            return response()->json(['message' => 'Espaço dos PDFs da conta cheio (até ' . self::MAX_ARQUIVOS . ' arquivos e 1 GB). Apague cartões de PDF que não usa mais.'], 422);
        }

        $tmp = $disco->path("$pasta/junto.pdf");
        $saida = fopen($tmp, 'wb');
        for ($i = 0; $i < $dados['partes']; $i++) {
            $entrada = fopen($disco->path("$pasta/$i.parte"), 'rb');
            stream_copy_to_stream($entrada, $saida);
            fclose($entrada);
        }
        fclose($saida);
        $cabeca = (string) file_get_contents($tmp, false, null, 0, 5);
        if (! str_starts_with($cabeca, '%PDF')) {
            $disco->deleteDirectory($pasta);
            return response()->json(['message' => 'O arquivo não é um PDF.'], 422);
        }
        $disco->makeDirectory(dirname($destino));
        $final = $disco->path($destino);
        if (is_file($final)) @unlink($final);
        rename($tmp, $final);
        $disco->deleteDirectory($pasta);

        return response()->json(['ok' => true, 'versao' => substr(sha1_file($final), 0, 12), 'tamanho' => $soma]);
    }

    public function ver(Request $request, string $area, string $arquivo): BinaryFileResponse
    {
        $caminho = $this->caminho($this->dono($request, $area), $area, $arquivo);
        abort_unless(Storage::disk('local')->exists($caminho), 404);

        // o endereço leva ?v=<versão>: o mesmo endereço nunca muda de conteúdo, então pode ficar em cache
        return response()->file(Storage::disk('local')->path($caminho), [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => 'inline',
            'Cache-Control'          => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function remover(Request $request, string $area, string $arquivo): JsonResponse
    {
        Storage::disk('local')->delete($this->caminho($this->dono($request, $area), $area, $arquivo));

        return response()->json(['ok' => true]);
    }

    private function dono(Request $request, string $area): int
    {
        abort_unless(in_array($area, self::AREAS, true), 404);

        return Compartilhar::dono($request, $area);
    }

    private function caminho(int $dono, string $area, string $arquivo): string
    {
        return "editorpdf/$dono/$area/$arquivo.pdf";
    }

    private function pastaEnvio(int $dono, string $area, string $arquivo, string $envio): string
    {
        return "editorpdf-envios/$dono/$area/$arquivo-$envio";
    }

    /** [quantidade de PDFs, bytes somados] da conta. */
    private function uso(int $dono): array
    {
        $disco = Storage::disk('local');
        $qtd = 0;
        $total = 0;
        foreach ($disco->allFiles("editorpdf/$dono") as $f) {
            $qtd++;
            $total += $disco->size($f);
        }

        return [$qtd, $total];
    }

    /** Envios que pararam no meio (aba fechada, rede caiu) somem depois de um dia. */
    private function limparEnviosVelhos(int $dono, string $area): void
    {
        $disco = Storage::disk('local');
        foreach ($disco->directories("editorpdf-envios/$dono/$area") as $d) {
            if (@filemtime($disco->path($d)) < time() - 86400) {
                $disco->deleteDirectory($d);
            }
        }
    }
}
