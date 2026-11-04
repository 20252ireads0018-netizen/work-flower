<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Support\Compartilhar;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use App\Support\Armazenamento\Arquivos;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persistência da área Mente.
 * - estado e imagens ficam em corpo_dados com a chave prefixada por "mente."
 * - PDFs ficam em storage/app/mente/pdf/{usuario}/{id}.pdf
 */
class MenteController extends Controller
{
    private const PREFIXO = 'mente.';

    /** Livros com PDF guardado por conta (cada um vai até 40 MB). */
    private const MAX_PDFS = 300;

    /** Dados salvos do usuário, sem o prefixo (estado, img.xxx). Usado pela view. */
    public static function dados(int $usuarioId): Collection
    {
        return CorpoDado::where('user_id', $usuarioId)
            ->where('chave', 'like', self::PREFIXO . '%')
            ->pluck('valor', 'chave')
            ->mapWithKeys(fn ($valor, $chave) => [substr($chave, strlen(self::PREFIXO)) => $valor]);
    }

    public function salvar(Request $request, string $chave): JsonResponse
    {
        $request->validate(['valor' => ['required', 'string', 'max:8000000']]);

        return Sincronia::gravar($request, 'mente', Compartilhar::dono($request, 'mente'), $chave, $request->input('valor'));
    }

    public function apagar(Request $request, string $chave): JsonResponse
    {
        CorpoDado::where('user_id', Compartilhar::dono($request, 'mente'))
            ->where('chave', self::PREFIXO . $chave)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function enviarPdf(Request $request, string $id): JsonResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:40960']]); // 40 MB

        // limite de livros com PDF por conta (trocar o PDF de um livro que já tem continua valendo)
        $disco = Storage::disk('local');
        if (! $disco->exists($this->pasta($request) . '/' . $id . '.pdf') && count($disco->files($this->pasta($request))) >= self::MAX_PDFS) {
            return response()->json(['message' => 'Limite de ' . self::MAX_PDFS . ' PDFs atingido. Remova o PDF de algum livro para enviar outro.'], 422);
        }

        $request->file('pdf')->storeAs($this->pasta($request), $id . '.pdf', 'local');

        return response()->json(['ok' => true]);
    }

    public function pdf(Request $request, string $id): Response
    {
        $caminho = $this->pasta($request) . '/' . $id . '.pdf';
        if (! Storage::disk('local')->exists($caminho)) {
            // PDF levado para o armazenamento da conta (Configurações › Armazenamento)
            $movido = Arquivos::pdfMovido(Compartilhar::dono($request, 'mente'), $id);
            abort_unless($movido && $movido->destino, 404);

            return ArmazenamentoController::entregar($movido);
        }

        return response()->file(Storage::disk('local')->path($caminho), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    public function removerPdf(Request $request, string $id): JsonResponse
    {
        Storage::disk('local')->delete($this->pasta($request) . '/' . $id . '.pdf');
        Arquivos::esquecerPdf(Compartilhar::dono($request, 'mente'), $id);

        return response()->json(['ok' => true]);
    }

    private function pasta(Request $request): string
    {
        return 'mente/pdf/' . Compartilhar::dono($request, 'mente'); // PDFs do dono do workflow
    }
}