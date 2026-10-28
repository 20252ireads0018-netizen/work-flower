<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Support\Planos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Conta os downloads feitos pelo botão de baixar dos cartões e barra o plano gratuito no limite. */
class DownloadController extends Controller
{
    private const CHAVE = 'downloads_feitos';

    public function registrar(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $limite  = Planos::limites(Planos::slugDe($usuario))['elementos'] ?? null;

        // planos sem limite: nada a contar
        if ($limite === null) {
            return response()->json(['ok' => true, 'feitos' => null, 'limite' => null]);
        }

        $tabela = (new CorpoDado)->getTable();

        [$liberado, $feitos] = DB::transaction(function () use ($usuario, $tabela, $limite) {
            $linha = DB::table($tabela)
                ->where('user_id', $usuario->id)
                ->where('chave', self::CHAVE)
                ->lockForUpdate()
                ->first();

            $feitos = $linha ? (int) json_decode((string) $linha->valor, true) : 0;

            if ($feitos >= $limite) {
                return [false, $feitos];
            }

            $feitos++;
            $agora = now();

            if ($linha) {
                DB::table($tabela)->where('id', $linha->id)->update([
                    'valor'      => (string) $feitos,
                    'updated_at' => $agora,
                ]);
            } else {
                DB::table($tabela)->insert([
                    'user_id'    => $usuario->id,
                    'chave'      => self::CHAVE,
                    'valor'      => (string) $feitos,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            return [true, $feitos];
        });

        if (! $liberado) {
            return response()->json([
                'plano_limite' => true,
                'recurso'      => 'downloads',
                'limite'       => $limite,
                'message'      => "Você já fez os {$limite} downloads do plano gratuito. Assine o Básico para baixar sem limite.",
                'redirect'     => route('planos'),
            ], 403);
        }

        return response()->json(['ok' => true, 'feitos' => $feitos, 'limite' => $limite]);
    }
}
