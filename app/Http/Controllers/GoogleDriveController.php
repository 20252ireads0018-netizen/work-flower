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
 * Também navega pelas subpastas, cria pasta/Documento/Planilha/Apresentação, move, manda para a lixeira (e restaura
 * ou apaga de vez) e baixa. Tudo dentro do escopo drive.file: só o que o próprio site criou ou abriu.
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

    /** Maior arquivo que o botão Baixar repassa (o site segura o arquivo na memória antes de mandar). */
    public const MAX_BAIXAR = 25 * 1024 * 1024;

    /** Id de arquivo ou pasta do Drive. */
    private const ID = '/^[A-Za-z0-9_-]{10,100}$/';

    /** Arquivos novos do Google que o cartão cria (vazios, com nome). */
    private const NOVOS = [
        'pasta'        => [GoogleDrive::PASTA, 'Pasta'],
        'documento'    => ['application/vnd.google-apps.document', 'Documento'],
        'planilha'     => ['application/vnd.google-apps.spreadsheet', 'Planilha'],
        'apresentacao' => ['application/vnd.google-apps.presentation', 'Apresentação'],
    ];

    /** Arquivos do Google baixados no formato do Office. */
    private const BAIXAR_COMO = [
        'application/vnd.google-apps.document'     => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'application/vnd.google-apps.spreadsheet'  => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'application/vnd.google-apps.presentation' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx'],
        'application/vnd.google-apps.drawing'      => ['image/png', 'png'],
    ];

    /** GET /google/drive[?q=busca][&pasta=id][&lixeira=1] */
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
        $pasta = (string) $request->query('pasta', '');
        if ($pasta !== '' && ! preg_match(self::ID, $pasta)) {
            return response()->json(['ok' => false, 'erro' => 'Pasta inválida.'] + $info + $base, 422);
        }
        try {
            $cli = $destino->cliente();
            $arquivos = $cli->listar(trim((string) $request->query('q', '')), 200, $pasta ?: null, $request->boolean('lixeira'));
            $raiz = $cli->raiz();
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()] + $info + $base, 422);
        }

        return response()->json(['ok' => true, 'arquivos' => $arquivos, 'raiz' => $raiz] + $info + $base);
    }

    /** GET /google/drive/pastas: pastas que o site enxerga (destino do "Mover"). */
    public function pastas(Request $request): JsonResponse
    {
        $destino = $this->exigir($request);
        try {
            $cli = $destino->cliente();

            return response()->json(['ok' => true, 'raiz' => $cli->raiz(), 'pastas' => $cli->pastas()]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    /** POST /google/drive/novo { tipo: pasta|documento|planilha|apresentacao, nome, pasta? }: cria vazio e devolve o link para abrir. */
    public function criar(Request $request): JsonResponse
    {
        $destino = $this->exigir($request);
        $d = $request->validate([
            'tipo'  => ['required', 'in:' . implode(',', array_keys(self::NOVOS))],
            'nome'  => ['nullable', 'string', 'max:120'],
            'pasta' => ['nullable', 'string', 'regex:' . self::ID],
        ], ['tipo.in' => 'Tipo de arquivo inválido.']);
        [$mime, $padrao] = self::NOVOS[$d['tipo']];
        $nome = self::nome((string) ($d['nome'] ?? '')) ?: $padrao . ' sem título';

        try {
            $f = $destino->cliente()->criar($nome, $mime, $d['pasta'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'arquivo' => $f, 'msg' => "Pronto: “{$nome}” está no seu Drive."]);
    }

    /** PATCH /google/drive/arquivos/{id}/mover { pasta } */
    public function mover(Request $request, string $id): JsonResponse
    {
        $destino = $this->exigir($request);
        $d = $request->validate(['pasta' => ['required', 'string', 'regex:' . self::ID]], ['pasta.required' => 'Escolha a pasta de destino.']);
        if ($d['pasta'] === $id) {
            return response()->json(['ok' => false, 'erro' => 'Uma pasta não pode ir para dentro dela mesma.'], 422);
        }
        try {
            $cli = $destino->cliente();
            if ($id === $cli->raiz()) {
                return response()->json(['ok' => false, 'erro' => 'A pasta Work Flower fica onde está.'], 422);
            }
            $cli->mover($id, $d['pasta']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'msg' => 'Arquivo movido.']);
    }

    /** PATCH /google/drive/arquivos/{id}/lixeira { lixeira: true|false }: manda para a lixeira do Drive ou restaura. */
    public function lixeira(Request $request, string $id): JsonResponse
    {
        $destino = $this->exigir($request);
        $d = $request->validate(['lixeira' => ['required', 'boolean']]);
        try {
            $cli = $destino->cliente();
            if ($id === $cli->raiz()) {
                return response()->json(['ok' => false, 'erro' => 'A pasta Work Flower não vai para a lixeira (é onde ficam os backups).'], 422);
            }
            $cli->lixeira($id, (bool) $d['lixeira']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'msg' => $d['lixeira'] ? 'Foi para a lixeira do Drive (dá para restaurar em “Lixeira”).' : 'Arquivo restaurado.']);
    }

    /** DELETE /google/drive/arquivos/{id}: apaga de vez. Só o que já está na lixeira, para não sumir nada sem querer. */
    public function apagar(Request $request, string $id): JsonResponse
    {
        $destino = $this->exigir($request);
        try {
            $cli = $destino->cliente();
            $f = $cli->info($id);
            if (empty($f['trashed'])) {
                return response()->json(['ok' => false, 'erro' => 'Mande para a lixeira primeiro; de lá dá para apagar de vez.'], 422);
            }
            $cli->apagar($id);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'msg' => '“' . ($f['name'] ?? 'Arquivo') . '” apagado de vez do Drive.']);
    }

    /** GET /google/drive/arquivos/{id}/baixar: o arquivo (Documentos, Planilhas e Apresentações do Google saem em .docx/.xlsx/.pptx). */
    public function baixar(Request $request, string $id)
    {
        $destino = $this->exigir($request);
        try {
            $cli = $destino->cliente();
            $f = $cli->info($id);
            $mime = (string) ($f['mimeType'] ?? '');
            $nome = (string) ($f['name'] ?? 'arquivo');
            if (isset(self::BAIXAR_COMO[$mime])) {
                [$para, $ext] = self::BAIXAR_COMO[$mime];
                $bruto = $cli->exportar($id, $para);
                $mime = $para;
                $nome .= '.' . $ext;
            } elseif (str_starts_with($mime, 'application/vnd.google-apps.')) {
                return response()->json(['ok' => false, 'erro' => 'Esse tipo não dá para baixar por aqui: abra no Google Drive.'], 422);
            } elseif ((int) ($f['size'] ?? 0) > self::MAX_BAIXAR) {
                return response()->json(['ok' => false, 'erro' => 'Arquivo grande demais para baixar por aqui (até ' . Uso::legivel(self::MAX_BAIXAR) . '): abra no Google Drive.'], 422);
            } else {
                $bruto = $cli->baixar($id);
            }
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        $seguro = preg_replace('/[^\w.\- ]+/u', '_', $nome) ?: 'arquivo';

        return response($bruto, 200, [
            'Content-Type'           => $mime ?: 'application/octet-stream',
            'Content-Disposition'    => "attachment; filename=\"{$seguro}\"; filename*=UTF-8''" . rawurlencode($nome),
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
            'pasta' => ['nullable', 'string', 'regex:' . self::ID], // subpasta aberta no cartão
        ], ['nome.required' => 'Dê um nome ao arquivo.', 'b64.max' => 'Cartão grande demais para mandar ao Drive.']);

        $conteudo = base64_decode($d['b64'], true);
        if ($conteudo === false) {
            return response()->json(['ok' => false, 'erro' => 'Conteúdo inválido.'], 422);
        }
        $nome = self::nome($d['nome']) ?: 'cartao';

        try {
            $id = $destino->cliente()->enviar($nome, $conteudo, $d['mime'], $d['pasta'] ?? null);
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
        $nome = self::nome($d['nome']);
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

    /** Nome de arquivo sem barras nem caracteres que o Windows recusa (o arquivo pode ser baixado depois). */
    private static function nome(string $n): string
    {
        return trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '-', $n), ' .-');
    }

    private static function utf8(string $t): string
    {
        if (str_starts_with($t, "\xEF\xBB\xBF")) {
            $t = substr($t, 3);
        }

        return mb_check_encoding($t, 'UTF-8') ? $t : mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }
}
