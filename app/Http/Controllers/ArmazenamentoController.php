<?php

namespace App\Http\Controllers;

use App\Models\ArmazenamentoArquivo;
use App\Models\ArmazenamentoBackup;
use App\Models\ArmazenamentoDestino;
use App\Support\Armazenamento\Arquivos;
use App\Support\Armazenamento\Backups;
use App\Support\Armazenamento\GoogleDrive;
use App\Support\Armazenamento\S3;
use App\Support\Armazenamento\Uso;
use App\Support\EmBreve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configurações › Armazenamento: quanto a conta ocupa, destinos próprios (bucket S3/R2/B2 ou Google Drive),
 * backups dos workflows (na hora, diário e restauração) e arquivos grandes fora do banco do site.
 */
class ArmazenamentoController extends Controller
{
    private const MAX_DESTINOS = 5;

    public function index(Request $request): View
    {
        $uid = $request->user()->id;

        return view('armazenamento.index', [
            'uso'        => Uso::calcular($request->user()),
            'destinos'   => ArmazenamentoDestino::where('user_id', $uid)->orderBy('id')->get(),
            'backups'    => ArmazenamentoBackup::where('user_id', $uid)->with('destino:id,nome')->latest('id')->take(30)->get(),
            'movidos'    => ArmazenamentoArquivo::where('user_id', $uid)->count(),
            'pendentes'  => Arquivos::pendentes($uid),
            'googleOk'   => EmBreve::driveLiberado($request->user()),
            'areas'      => WorkflowTransferController::AREAS,
        ]);
    }

    // ------------------------------------------------------------- destinos

    public function salvarS3(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'nome'     => ['nullable', 'string', 'max:60'],
            'endpoint' => ['nullable', 'url:https', 'max:200'],
            'regiao'   => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'bucket'   => ['required', 'string', 'max:63', 'regex:/^[a-z0-9][a-z0-9.-]{1,62}$/'],
            'chave'    => ['required', 'string', 'max:200'],
            'segredo'  => ['required', 'string', 'max:200'],
            'prefixo'  => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]*$/'],
        ], [], ['chave' => 'chave de acesso', 'segredo' => 'chave secreta', 'prefixo' => 'pasta']);
        $this->exigirVaga($request);

        $credenciais = [
            'endpoint' => trim((string) ($d['endpoint'] ?? '')),
            'regiao'   => trim((string) ($d['regiao'] ?? '')),
            'bucket'   => $d['bucket'],
            'chave'    => trim($d['chave']),
            'segredo'  => trim($d['segredo']),
            'prefixo'  => trim((string) ($d['prefixo'] ?? 'work-flower'), '/') ?: 'work-flower',
        ];

        // o site faz as requisições: endereço da rede interna do servidor não é aceito
        if ($credenciais['endpoint'] !== '' && ! $this->hostPublico((string) parse_url($credenciais['endpoint'], PHP_URL_HOST))) {
            throw ValidationException::withMessages(['endpoint' => 'Use o endereço público do serviço (ex.: https://<conta>.r2.cloudflarestorage.com).']);
        }

        try {
            (new S3($credenciais))->testar();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['s3' => $e->getMessage()]);
        }

        $host = parse_url($credenciais['endpoint'] ?: 'https://s3.amazonaws.com', PHP_URL_HOST);
        ArmazenamentoDestino::create([
            'user_id'     => $request->user()->id,
            'tipo'        => 's3',
            'nome'        => trim((string) ($d['nome'] ?? '')) ?: $this->nomeServico($host),
            'credenciais' => $credenciais,
            'resumo'      => $credenciais['bucket'] . '/' . $credenciais['prefixo'] . ' · ' . $host,
        ]);

        return back()->with('ok', 'Bucket conectado. A conexão foi testada gravando e apagando um arquivo.');
    }

    public function atualizar(Request $request, ArmazenamentoDestino $destino): RedirectResponse
    {
        $this->meu($request, $destino);
        $d = $request->validate([
            'nome'          => ['sometimes', 'string', 'max:60'],
            'backup_diario' => ['sometimes', 'boolean'],
        ]);
        $destino->update($d);

        return back()->with('ok', isset($d['backup_diario'])
            ? ($d['backup_diario'] ? 'Backup diário ligado para ' . $destino->nome . '.' : 'Backup diário desligado.')
            : 'Destino atualizado.');
    }

    public function testar(Request $request, ArmazenamentoDestino $destino): RedirectResponse
    {
        $this->meu($request, $destino);
        try {
            $destino->cliente()->testar();
            $destino->update(['erro' => null]);
        } catch (RuntimeException $e) {
            $destino->update(['erro' => mb_substr($e->getMessage(), 0, 300)]);

            return back()->withErrors(['destino' => $e->getMessage()]);
        }

        return back()->with('ok', $destino->nome . ' está funcionando.');
    }

    public function remover(Request $request, ArmazenamentoDestino $destino): RedirectResponse
    {
        $this->meu($request, $destino);
        if ($destino->arquivosMovidos()->exists()) {
            return back()->withErrors(['destino' => 'Há imagens ou PDFs guardados em ' . $destino->nome . '. Traga os arquivos de volta antes de desconectar.']);
        }

        if ($destino->tipo === 'gdrive') (new GoogleDrive($destino))->revogar();
        $destino->delete();

        return back()->with('ok', 'Destino desconectado. Os backups que já estavam lá continuam no seu bucket/Drive.');
    }

    // --------------------------------------------------------- Google Drive

    public function googleConectar(Request $request): RedirectResponse
    {
        if (! EmBreve::driveLiberado($request->user())) {
            return redirect()->route('armazenamento')->withErrors(['destino' => 'O Google Drive ainda não foi liberado neste site.']);
        }
        $this->exigirVaga($request);

        $estado = Str::random(40);
        $request->session()->put('armazenamento.google_estado', $estado);
        // veio do cartão Google Drive do quadro: volta para a mesma página depois do Google
        $request->session()->put('armazenamento.google_voltar', $request->query('de') === 'cartao' ? YoutubeController::voltar($request) : null);

        return redirect()->away(GoogleDrive::urlAutorizacao($estado, GoogleDrive::retorno()));
    }

    public function googleRetorno(Request $request): RedirectResponse
    {
        $esperado = $request->session()->pull('armazenamento.google_estado');
        if (! $esperado || ! hash_equals($esperado, (string) $request->query('state'))) {
            return redirect()->route('armazenamento')->withErrors(['destino' => 'A conexão com o Google expirou. Tente de novo.']);
        }
        if (! $request->filled('code')) {
            return redirect()->route('armazenamento')->withErrors(['destino' => 'A conexão com o Google Drive foi cancelada.']);
        }

        try {
            $this->exigirVaga($request);
            $credenciais = GoogleDrive::trocarCodigo((string) $request->query('code'), GoogleDrive::retorno());
            $destino = new ArmazenamentoDestino([
                'user_id'     => $request->user()->id,
                'tipo'        => 'gdrive',
                'nome'        => 'Google Drive',
                'credenciais' => $credenciais,
                'resumo'      => ($credenciais['email'] ?? 'Conta Google') . ' · pasta Work Flower',
            ]);
            $destino->save();
            $destino->cliente()->testar(); // cria a pasta Work Flower
        } catch (RuntimeException $e) {
            return redirect()->route('armazenamento')->withErrors(['destino' => $e->getMessage()]);
        }

        $voltar = $request->session()->pull('armazenamento.google_voltar');

        return redirect($voltar ?: route('armazenamento'))->with('ok', 'Google Drive conectado. Os arquivos ficam na pasta "Work Flower" do seu Drive.');
    }

    // -------------------------------------------------------------- backups

    public function backup(Request $request, ArmazenamentoDestino $destino): RedirectResponse
    {
        $this->meu($request, $destino);
        try {
            $b = Backups::fazer($destino);
        } catch (RuntimeException $e) {
            return back()->withErrors(['destino' => $e->getMessage()]);
        }

        return back()->with('ok', trim('Backup enviado para ' . $destino->nome . ' (' . Uso::legivel((int) $b->tamanho) . '). ' . implode(' ', Backups::$avisos)));
    }

    public function restaurar(Request $request, ArmazenamentoBackup $backup): RedirectResponse
    {
        abort_unless((int) $backup->user_id === (int) $request->user()->id, 404);
        $d = $request->validate([
            'areas'   => ['required', 'array', 'min:1'],
            'areas.*' => ['string', 'in:' . implode(',', array_keys(WorkflowTransferController::AREAS))],
        ], ['areas.required' => 'Marque ao menos uma área para restaurar.']);

        try {
            $nomes = Backups::restaurar($backup, array_values(array_unique($d['areas'])));
        } catch (RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('ok', 'Restaurado: ' . implode(', ', $nomes) . '. Uma cópia de como estava antes ficou no mesmo destino.');
    }

    /** Baixa o arquivo do backup para o computador (dá para importar pelo menu Importar / exportar). */
    public function baixarBackup(Request $request, ArmazenamentoBackup $backup): Response
    {
        abort_unless((int) $backup->user_id === (int) $request->user()->id, 404);
        try {
            $conteudo = $backup->destino->cliente()->baixar($backup->remoto);
        } catch (RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return response()->streamDownload(fn () => print($conteudo), $backup->nome, ['Content-Type' => 'application/json']);
    }

    // ------------------------------------------------------------- arquivos

    public function mover(Request $request): RedirectResponse
    {
        $d = $request->validate(['destino_id' => ['required', 'integer']]);
        $destino = ArmazenamentoDestino::where('user_id', $request->user()->id)->findOrFail($d['destino_id']);

        try {
            [$n, $bytes, $restam] = Arquivos::mover($destino);
        } catch (RuntimeException $e) {
            return back()->withErrors(['arquivos' => $e->getMessage()]);
        }

        $msg = $n ? "{$n} " . ($n === 1 ? 'arquivo levado' : 'arquivos levados') . ' para ' . $destino->nome . ' (' . Uso::legivel($bytes) . ' a menos no site).'
                  : 'Nenhum arquivo grande para levar.';
        if ($restam) $msg .= " Ainda faltam {$restam}: clique de novo para continuar.";

        return back()->with('ok', $msg);
    }

    public function trazer(Request $request): RedirectResponse
    {
        try {
            [$n, $restam] = Arquivos::trazer($request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['arquivos' => $e->getMessage()]);
        }

        $msg = "{$n} " . ($n === 1 ? 'arquivo voltou' : 'arquivos voltaram') . ' para o site.';
        if ($restam) $msg .= " Ainda faltam {$restam}: clique de novo para continuar.";

        return back()->with('ok', $msg);
    }

    /** Imagem movida: o quadro pede /armazenamento/arquivo/{token} como src. */
    public function arquivo(Request $request, string $token): Response
    {
        $a = ArmazenamentoArquivo::where('token', $token)->with('destino')->first();
        abort_unless($a && $a->destino && Arquivos::podeVer($a, (int) $request->user()->id), 404);

        return self::entregar($a);
    }

    /** Responde com o arquivo: redireciona para um link temporário (S3) ou repassa o conteúdo (Drive). */
    public static function entregar(ArmazenamentoArquivo $a, string $disposicao = 'inline'): Response
    {
        try {
            $cliente = $a->destino->cliente();
            if ($url = $cliente->urlTemporaria($a->remoto, 600)) {
                return redirect()->away($url)->header('Cache-Control', 'private, max-age=300');
            }
            $conteudo = $cliente->baixar($a->remoto);
        } catch (RuntimeException $e) {
            report($e);
            abort(502, 'Não consegui buscar o arquivo no seu armazenamento.');
        }

        return response($conteudo, 200, [
            'Content-Type'            => $a->mime,
            'Content-Disposition'     => $disposicao,
            'Cache-Control'           => 'private, max-age=3600',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    // ---------------------------------------------------------------- apoio

    private function meu(Request $request, ArmazenamentoDestino $destino): void
    {
        abort_unless((int) $destino->user_id === (int) $request->user()->id, 404);
    }

    private function exigirVaga(Request $request): void
    {
        if (ArmazenamentoDestino::where('user_id', $request->user()->id)->count() >= self::MAX_DESTINOS) {
            throw ValidationException::withMessages(['destino' => 'Dá para conectar até ' . self::MAX_DESTINOS . ' destinos. Desconecte um para adicionar outro.']);
        }
    }

    private function hostPublico(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) return false;

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) return true; // nome que não resolve: o teste de conexão vai falhar sozinho

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function nomeServico(?string $host): string
    {
        $host = (string) $host;

        return match (true) {
            str_contains($host, 'r2.cloudflarestorage.com') => 'Cloudflare R2',
            str_contains($host, 'backblazeb2.com')          => 'Backblaze B2',
            str_contains($host, 'amazonaws.com')            => 'AWS S3',
            str_contains($host, 'wasabisys.com')            => 'Wasabi',
            default                                         => 'Bucket S3',
        };
    }
}
