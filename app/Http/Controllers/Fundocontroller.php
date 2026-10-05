<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Fundo personalizado do painel (cor, imagem, ajuste, escurecer e desfoque).
 * Usa a tabela corpo_dados: chave "fundo" guarda as opções (JSON) e "fundo.img" guarda a imagem.
 */
class FundoController extends Controller
{
    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function salvar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'cor'            => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ajuste'         => ['required', 'in:cover,contain,repeat'],
            'escurecer'      => ['required', 'integer', 'between:0,90'],
            'desfoque'       => ['required', 'integer', 'between:0,20'],
            'imagem'         => ['nullable', 'string', 'max:6000000'],
            'remover_imagem' => ['nullable', 'boolean'],
        ], [
            'cor.regex'      => 'Escolha uma cor válida.',
            'imagem.max'     => 'A imagem ficou grande demais. Escolha uma menor.',
        ]);

        $usuarioId = $request->user()->id;

        if (! empty($dados['imagem'])) {
            $this->conferirImagem($dados['imagem']);

            CorpoDado::updateOrCreate(
                ['user_id' => $usuarioId, 'chave' => 'fundo.img'],
                ['valor' => $dados['imagem']],
            );
        } elseif (! empty($dados['remover_imagem'])) {
            CorpoDado::where('user_id', $usuarioId)->where('chave', 'fundo.img')->delete();
        }

        CorpoDado::updateOrCreate(
            ['user_id' => $usuarioId, 'chave' => 'fundo'],
            ['valor' => json_encode([
                'cor'       => $dados['cor'] ?? null,
                'ajuste'    => $dados['ajuste'],
                'escurecer' => (int) $dados['escurecer'],
                'desfoque'  => (int) $dados['desfoque'],
            ])],
        );

        $linha = CorpoDado::where('user_id', $usuarioId)->where('chave', 'fundo.img')->first(['id', 'updated_at']);

        return response()->json([
            'ok'     => true,
            'imagem' => (bool) $linha,
            'versao' => $linha?->updated_at?->timestamp ?? 0,
        ]);
    }

    /** Entrega a imagem como arquivo de verdade, para o navegador guardar em cache. */
    public function imagem(Request $request): Response
    {
        $linha = CorpoDado::where('user_id', $request->user()->id)
            ->where('chave', 'fundo.img')
            ->first();

        abort_if(! $linha, 404);

        $pos = strpos($linha->valor, ';base64,');
        abort_if($pos === false, 404);

        $tipo = substr($linha->valor, 5, $pos - 5); // "image/jpeg"
        $bin  = base64_decode(substr($linha->valor, $pos + 8), true);

        abort_if($bin === false || ! in_array($tipo, self::TIPOS, true), 404);

        return response($bin, 200, [
            'Content-Type'           => $tipo,
            'Cache-Control'          => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Só aceita JPG, PNG, WebP ou GIF e confere se o conteúdo é mesmo uma imagem. */
    private function conferirImagem(string $url): void
    {
        $erro = ValidationException::withMessages(['imagem' => 'Use uma imagem JPG, PNG, WebP ou GIF.']);

        $pos = strpos($url, ';base64,');
        if ($pos === false) {
            throw $erro;
        }

        $tipo = substr($url, 5, $pos - 5);
        if (! str_starts_with($url, 'data:') || ! in_array($tipo, self::TIPOS, true)) {
            throw $erro;
        }

        $bin = base64_decode(substr($url, $pos + 8), true);
        if ($bin === false) {
            throw $erro;
        }

        $info = @getimagesizefromstring($bin);
        if ($info === false || ! in_array($info['mime'] ?? '', self::TIPOS, true)) {
            throw $erro;
        }
    }
}