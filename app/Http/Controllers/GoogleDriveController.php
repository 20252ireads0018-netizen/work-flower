<?php

namespace App\Http\Controllers;

use App\Models\ArmazenamentoDestino;
use App\Support\Armazenamento\Backups;
use App\Support\Armazenamento\GoogleDrive;
use App\Support\Armazenamento\Uso;
use App\Support\EmBreve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Cartão "Google Drive" do quadro (public/js/integracoes-cartoes.js): a pasta "Work Flower" do Drive da pessoa
 * como se fosse do próprio site. Lista os arquivos (backups, cartões enviados, arquivos levados), traz um arquivo
 * para o quadro (texto, tabela, imagem ou o próprio cartão), manda um cartão para o Drive e faz backup na hora.
 * A conexão é a mesma de Configurações › Armazenamento (ArmazenamentoDestino tipo "gdrive"); sem ela, o cartão
 * mostra o botão de conectar.
 */
class GoogleDriveController extends Controller
{
    /** Maior arquivo que o cartão traz para o quadro (texto e imagem vão inteiros no estado do quadro). */
    public const MAX_TRAZER = 6 * 1024 * 1024;

    /** Maior arquivo que o cartão manda (um cartão em JSON, CSV ou Markdown). */
    public const MAX_ENVIAR = 8 * 1024 * 1024;

    private const EXPORTAR = [
        'application/vnd.google-apps.document'     => ['text/plain', 'texto'],
        'application/vnd.google-apps.spreadsheet'  => ['text/csv', 'csv'],
        'application/vnd.google-apps.presentation' => ['text/plain', 'texto'],
    ];

    /** GET /google/drive[?q=busca] */
    public function lista(Request $request): JsonResponse
    {
        $u = $request->user();
        $destino = $this->destino($request);
        $base = [
            'liberado' => EmBreve::driveLiberado($u),
            'conectar' => EmBreve::driveLiberado($u) ? route('armazenamento.google') : null,
            'pagina'   => route('armazenamento'),
        ];
        if (! $destino) {
            return response()->json(['ok' => true, 'conectado' => false] + $base);
        }

        $info = [
            'conectado'     => true,
            'email'         => $destino->credenciais['email'] ?? null,
            'destino'       => $destino->id,
            'backup_diario' => (bool) $destino->backup_diario,
            'ultimo_backup' => $destino->ultimo_backup_em?->toIso8601String(),
            'erro_destino'  => $destino->erro,
        ];
        try {
            $arquivos = $destino->cliente()->listar(trim((string) $request->query('q', '')));
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()] + $info + $base, 422);
        }

        return response()->json(['ok' => true, 'arquivos' => $arquivos] + $info + $base);
    }

    /**
     * GET /google/drive/arquivos/{id}: conteúdo pronto para virar cartão.
     * { tipo: texto|csv|json|imagem|link, nome, mime, link, conteudo? (texto) | src? (data URL da imagem) }
     */
    public function arquivo(Request $request, string $id): JsonResponse
    {
        $destino = $this->exigir($request);
        try {
            $cli = $destino->cliente();
            $f = $cli->info($id);
            $mime = (string) ($f['mimeType'] ?? '');
            $nome = (string) ($f['name'] ?? 'Arquivo');
            $base = ['ok' => true, 'nome' => $nome, 'mime' => $mime, 'link' => $f['webViewLink'] ?? null];

            if (isset(self::EXPORTAR[$mime])) {
                [$para, $tipo] = self::EXPORTAR[$mime];
                $txt = $cli->exportar($id, $para);

                return response()->json($base + ['tipo' => $tipo, 'conteudo' => self::utf8(substr($txt, 0, self::MAX_TRAZER))]);
            }
            $tamanho = (int) ($f['size'] ?? 0);
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            $ehTexto = str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/xml'], true)
                || in_array($ext, ['md', 'markdown', 'txt', 'csv', 'tsv', 'json'], true);
            $ehImagem = in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true);

            if ((! $ehTexto && ! $ehImagem) || $tamanho > self::MAX_TRAZER) {
                return response()->json($base + ['tipo' => 'link', 'tamanho' => $tamanho,
                    'aviso' => $tamanho > self::MAX_TRAZER ? 'Arquivo grande demais para virar cartão (até ' . Uso::legivel(self::MAX_TRAZER) . ').' : null]);
            }

            $bruto = $cli->baixar($id);
            if ($ehImagem) {
                return response()->json($base + ['tipo' => 'imagem', 'src' => 'data:' . $mime . ';base64,' . base64_encode($bruto)]);
            }
            $tipo = in_array($ext, ['csv', 'tsv'], true) || $mime === 'text/csv' ? 'csv' : ($ext === 'json' || $mime === 'application/json' ? 'json' : 'texto');

            return response()->json($base + ['tipo' => $tipo, 'conteudo' => self::utf8($bruto)]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /google/drive/arquivos { nome, mime, b64 }: manda um cartão para a pasta Work Flower.
     * O conteúdo vem em base64: o mod_security da HostGator responde 406 a corpos que parecem código ou SQL.
     */
    public function enviar(Request $request): JsonResponse
    {
        $destino = $this->exigir($request);
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'mime' => ['required', 'in:text/markdown,text/plain,text/csv,application/json'],
            'b64'  => ['required', 'string', 'max:' . (int) ceil(self::MAX_ENVIAR * 4 / 3) + 8],
        ], ['nome.required' => 'Dê um nome ao arquivo.', 'b64.max' => 'Cartão grande demais para mandar ao Drive.']);

        $conteudo = base64_decode($d['b64'], true);
        if ($conteudo === false) {
            return response()->json(['ok' => false, 'erro' => 'Conteúdo inválido.'], 422);
        }
        $nome = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '-', $d['nome']), ' .-') ?: 'cartao';

        try {
            $id = $destino->cliente()->enviar($nome, $conteudo, $d['mime']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $id, 'msg' => "“{$nome}” salvo na pasta Work Flower do seu Drive."]);
    }

    /** PATCH /google/drive/arquivos/{id} { nome } */
    public function renomear(Request $request, string $id): JsonResponse
    {
        $destino = $this->exigir($request);
        $d = $request->validate(['nome' => ['required', 'string', 'max:120']]);
        $nome = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '-', $d['nome']), ' .-');
        if ($nome === '') {
            return response()->json(['ok' => false, 'erro' => 'Nome inválido.'], 422);
        }
        try {
            $destino->cliente()->renomear($id, $nome);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** POST /google/drive/backup: backup de todos os workflows agora, na pasta do Drive. */
    public function backup(Request $request): JsonResponse
    {
        $destino = $this->exigir($request);
        try {
            $b = Backups::fazer($destino);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'msg' => trim('Backup salvo no Drive (' . Uso::legivel((int) $b->tamanho) . '). ' . implode(' ', Backups::$avisos))]);
    }

    /** PATCH /google/drive/diario { ligado }: backup automático todo dia de madrugada. */
    public function diario(Request $request): JsonResponse
    {
        $destino = $this->exigir($request);
        $d = $request->validate(['ligado' => ['required', 'boolean']]);
        $destino->forceFill(['backup_diario' => (bool) $d['ligado']])->save();

        return response()->json(['ok' => true, 'backup_diario' => (bool) $destino->backup_diario]);
    }

    // ---------------------------------------------------------------- interno

    private function destino(Request $request): ?ArmazenamentoDestino
    {
        return ArmazenamentoDestino::where('user_id', $request->user()->id)->where('tipo', 'gdrive')->orderBy('id')->first();
    }

    private function exigir(Request $request): ArmazenamentoDestino
    {
        $d = $this->destino($request);
        abort_unless($d, 409, 'Conecte o Google Drive primeiro.');

        return $d;
    }

    private static function utf8(string $t): string
    {
        if (str_starts_with($t, "\xEF\xBB\xBF")) {
            $t = substr($t, 3);
        }

        return mb_check_encoding($t, 'UTF-8') ? $t : mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }
}
