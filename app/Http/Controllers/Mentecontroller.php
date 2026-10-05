<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Persistência da área Mente.
 * - estado e imagens ficam em corpo_dados com a chave prefixada por "mente."
 * - PDFs ficam em storage/app/mente/pdf/{usuario}/{id}.pdf
 */
class MenteController extends Controller
{
    private const PREFIXO = 'mente.';

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

        CorpoDado::updateOrCreate(
            ['user_id' => $request->user()->id, 'chave' => self::PREFIXO . $chave],
            ['valor' => $request->input('valor')]
        );

        return response()->json(['ok' => true]);
    }

    public function apagar(Request $request, string $chave): JsonResponse
    {
        CorpoDado::where('user_id', $request->user()->id)
            ->where('chave', self::PREFIXO . $chave)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function enviarPdf(Request $request, string $id): JsonResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:40960']]); // 40 MB

        $request->file('pdf')->storeAs($this->pasta($request), $id . '.pdf', 'local');

        return response()->json(['ok' => true]);
    }

    public function pdf(Request $request, string $id): BinaryFileResponse
    {
        $caminho = $this->pasta($request) . '/' . $id . '.pdf';
        abort_unless(Storage::disk('local')->exists($caminho), 404);

        return response()->file(Storage::disk('local')->path($caminho), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    public function removerPdf(Request $request, string $id): JsonResponse
    {
        Storage::disk('local')->delete($this->pasta($request) . '/' . $id . '.pdf');

        return response()->json(['ok' => true]);
    }

    private function pasta(Request $request): string
    {
        return 'mente/pdf/' . $request->user()->id;
    }
}