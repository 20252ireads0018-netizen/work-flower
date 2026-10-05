<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Biblioteca de blocos do usuário: vale para todas as páginas (Carteira, Corpo, Mente…).
 * Fica numa linha só de corpo_dados (chave "biblioteca"), como o fundo da página.
 */
class BibliotecaController extends Controller
{
    private const CHAVE = 'biblioteca';
    private const LIMITE_BYTES = 12_000_000;

    public function index(Request $request): JsonResponse
    {
        $valor = CorpoDado::where('user_id', $request->user()->id)
            ->where('chave', self::CHAVE)
            ->value('valor');

        $itens = json_decode((string) $valor, true);

        return response()->json(['itens' => is_array($itens) ? array_values($itens) : []]);
    }

    public function salvar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'itens'           => ['present', 'array', 'max:60'],
            'itens.*.id'      => ['required', 'string', 'max:40'],
            'itens.*.nome'    => ['required', 'string', 'max:120'],
            'itens.*.tipo'    => ['required', 'in:texto,tabela,lista,imagem,documento,mapa,vivo,compras,campos,escrita,social,loja,codigo'],
            'itens.*.titulo'  => ['nullable', 'string', 'max:200'],
            'itens.*.dados'   => ['nullable', 'array'], // itens antigos ficaram sem 'dados': aceita e completa abaixo
            'itens.*.cor'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'itens.*.w'       => ['nullable', 'integer', 'min:100', 'max:4000'],
            'itens.*.h'       => ['nullable', 'integer', 'min:80', 'max:4000'],
            'itens.*.imagem'  => ['nullable', 'string', 'starts_with:data:image/', 'max:1500000'],
            'itens.*.criado'  => ['nullable', 'string', 'max:40'],
        ]);

        // Todo item passa a ter 'dados' (os que ficaram sem conteúdo viram vazios e podem ser excluídos normalmente)
        $dados['itens'] = array_map(function ($item) {
            $item['dados'] = $item['dados'] ?? [];

            return $item;
        }, $dados['itens']);

        // Cartão ativo: confere a referência à mão. Regras aninhadas em 'itens.*.dados.*' fariam o Laravel
        // descartar o resto do conteúdo (dados) dos outros itens, então a checagem fica fora do validate().
        foreach ($dados['itens'] as $item) {
            if (($item['tipo'] ?? '') !== 'vivo') {
                continue;
            }
            $d = $item['dados'] ?? [];
            if (! preg_match('#^/areas/[a-z]+$#', (string) ($d['pagina'] ?? ''))
                || ! preg_match('/^[A-Za-z0-9._-]{1,60}$/', (string) ($d['sec'] ?? ''))
                || ! preg_match('/^[A-Za-z0-9._-]{1,60}$/', (string) ($d['bid'] ?? ''))) {
                return response()->json(['message' => 'Cartão ativo inválido.'], 422);
            }
        }

        $json = json_encode(array_values($dados['itens']), JSON_UNESCAPED_UNICODE);

        if (strlen($json) > self::LIMITE_BYTES) {
            return response()->json(['message' => 'Biblioteca cheia.'], 413);
        }

        CorpoDado::updateOrCreate(
            ['user_id' => $request->user()->id, 'chave' => self::CHAVE],
            ['valor' => $json],
        );

        return response()->json(['ok' => true]);
    }
}