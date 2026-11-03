<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Support\Ambiente;
use App\Support\Compartilhar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fundo e música de cada workflow e de cada setor (aba) dele (ver App\Support\Ambiente).
 * O setor vem em "setor" (na URL ou no corpo); sem ele, vale a área inteira.
 * Quem foi convidado vê e ouve o ambiente do dono; só o dono muda.
 */
class AmbienteController extends Controller
{
    /** Só o dono do workflow mexe no ambiente. */
    private function donoQueEdita(Request $request, string $area): int
    {
        $dono = Compartilhar::dono($request, $area);
        abort_unless($dono === $request->user()->id, 403, 'Só o dono do workflow muda o fundo e a música.');

        return $dono;
    }

    /** Quantas abas de uma área podem ter fundo e música próprios (o id da aba vem do navegador). */
    private const MAX_SETORES = 60;

    /** Barra uma aba nova quando a área já tem MAX_SETORES configuradas (evita encher banco e disco com ids inventados). */
    private function limiteSetores(int $dono, string $area, ?string $setor): void
    {
        if ($setor === null || CorpoDado::where('user_id', $dono)->where('chave', Ambiente::chave($area, $setor))->exists()) {
            return;
        }
        $usados = CorpoDado::where('user_id', $dono)->where('chave', 'like', "amb.{$area}.s.%")->where('chave', 'not like', '%.img')->count();
        if ($usados >= self::MAX_SETORES) {
            throw ValidationException::withMessages(['setor' => 'Esta área já tem ' . self::MAX_SETORES . ' abas com fundo e música próprios. Volte alguma para "Igual à área" antes.']);
        }
    }

    /** Setor pedido (null = a área inteira); um id fora do formato não é aceito. */
    private function setor(Request $request): ?string
    {
        $bruto = $request->input('setor');
        if ($bruto === null || $bruto === '') {
            return null;
        }
        $setor = Ambiente::setor($bruto);
        abort_if($setor === null, 404);

        return $setor;
    }

    public function salvar(Request $request, string $area): JsonResponse
    {
        $dono = $this->donoQueEdita($request, $area);
        $setor = $this->setor($request);
        $this->limiteSetores($dono, $area, $setor);

        $d = $request->validate([
            'fundo.proprio'        => ['required', 'boolean'],
            'fundo.cor'            => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fundo.ajuste'         => ['required', 'in:cover,contain,repeat'],
            'fundo.escurecer'      => ['required', 'integer', 'between:0,90'],
            'fundo.desfoque'       => ['required', 'integer', 'between:0,20'],
            'fundo.imagem'         => ['nullable', 'string', 'max:6000000'],
            'fundo.remover_imagem' => ['nullable', 'boolean'],
            'musica.tipo'          => ['required', $setor === null ? 'in:nenhuma,arquivo,youtube' : 'in:herdar,nenhuma,arquivo,youtube'],
            'musica.youtube'       => ['nullable', 'string', 'max:300'],
            'musica.volume'        => ['required', 'integer', 'between:0,100'],
            'musica.auto'          => ['required', 'boolean'],
        ], [
            'fundo.cor.regex'  => 'Escolha uma cor válida.',
            'fundo.imagem.max' => 'A imagem ficou grande demais. Escolha uma menor.',
        ]);

        $yt = null;
        if ($d['musica']['tipo'] === 'youtube') {
            $yt = Ambiente::idYoutube((string) ($d['musica']['youtube'] ?? ''));
            if (! $yt) {
                throw ValidationException::withMessages(['musica.youtube' => 'Cole um link de vídeo do YouTube (youtube.com/watch?v=… ou youtu.be/…).']);
            }
        }
        if ($d['musica']['tipo'] === 'arquivo' && ! Ambiente::caminhoAudio($dono, $area, $setor)) {
            throw ValidationException::withMessages(['musica.tipo' => 'Envie um arquivo de áudio antes de escolher essa opção.']);
        }

        $chaveImg = Ambiente::chave($area, $setor) . '.img';
        if (! empty($d['fundo']['imagem'])) {
            FundoController::validarImagem($d['fundo']['imagem']);
            CorpoDado::updateOrCreate(['user_id' => $dono, 'chave' => $chaveImg], ['valor' => $d['fundo']['imagem']]);
        } elseif (! empty($d['fundo']['remover_imagem'])) {
            CorpoDado::where('user_id', $dono)->where('chave', $chaveImg)->delete();
        }

        $antes = Ambiente::opcoes($dono, $area, $setor);
        Ambiente::gravar($dono, $area, $setor, [
            'fundo' => [
                'proprio'   => (bool) $d['fundo']['proprio'],
                'cor'       => $d['fundo']['cor'] ?? null,
                'ajuste'    => $d['fundo']['ajuste'],
                'escurecer' => (int) $d['fundo']['escurecer'],
                'desfoque'  => (int) $d['fundo']['desfoque'],
            ],
            'musica' => [
                'tipo'    => $d['musica']['tipo'],
                'youtube' => $yt,
                'nome'    => $antes['musica']['nome'],
                'volume'  => (int) $d['musica']['volume'],
                'auto'    => (bool) $d['musica']['auto'],
            ],
        ]);
        Ambiente::limparSeHerda($dono, $area, $setor);

        return response()->json(['ok' => true, 'setor' => $setor, 'ambiente' => Ambiente::paraPagina($dono, $area, $setor)]);
    }

    public function enviarAudio(Request $request, string $area): JsonResponse
    {
        $dono = $this->donoQueEdita($request, $area);
        $setor = $this->setor($request);
        $this->limiteSetores($dono, $area, $setor);

        $request->validate([
            'audio' => ['required', 'file', 'max:15360', // 15 MB
                'mimetypes:audio/mpeg,audio/mp3,audio/ogg,audio/wav,audio/x-wav,audio/wave,audio/mp4,audio/x-m4a,audio/aac,audio/webm,video/webm,application/ogg'],
        ], [
            'audio.max'       => 'O áudio pode ter até 15 MB.',
            'audio.mimetypes' => 'Use um arquivo de áudio MP3, OGG, WAV, M4A ou WebM.',
        ]);

        $arquivo = $request->file('audio');
        // confere o conteúdo de verdade (a extensão e o tipo enviados pelo navegador podem mentir)
        $real = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo->getRealPath());
        if (! str_starts_with($real, 'audio/') && ! in_array($real, ['video/webm', 'application/ogg'], true)) {
            throw ValidationException::withMessages(['audio' => 'Use um arquivo de áudio MP3, OGG, WAV, M4A ou WebM.']);
        }
        $ext = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->guessExtension() ?: 'mp3');
        if (! in_array($ext, Ambiente::AUDIO_EXT, true)) {
            $ext = 'mp3';
        }

        // um áudio por área ou setor: apaga o anterior (que pode ter outra extensão)
        if ($antigo = Ambiente::caminhoAudio($dono, $area, $setor)) {
            Storage::disk('local')->delete($antigo);
        }
        $arquivo->storeAs("ambiente/{$dono}", Ambiente::base($area, $setor) . ".{$ext}", 'local');

        $o = Ambiente::opcoes($dono, $area, $setor);
        $o['musica']['tipo'] = 'arquivo';
        $o['musica']['nome'] = mb_substr(pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME), 0, 120);
        Ambiente::gravar($dono, $area, $setor, $o);

        return response()->json(['ok' => true, 'setor' => $setor, 'ambiente' => Ambiente::paraPagina($dono, $area, $setor)]);
    }

    public function removerAudio(Request $request, string $area): JsonResponse
    {
        $dono = $this->donoQueEdita($request, $area);
        $setor = $this->setor($request);
        if ($antigo = Ambiente::caminhoAudio($dono, $area, $setor)) {
            Storage::disk('local')->delete($antigo);
        }
        $o = Ambiente::opcoes($dono, $area, $setor);
        $o['musica']['nome'] = null;
        if ($o['musica']['tipo'] === 'arquivo') {
            $o['musica']['tipo'] = $setor === null ? 'nenhuma' : 'herdar';
        }
        Ambiente::gravar($dono, $area, $setor, $o);
        Ambiente::limparSeHerda($dono, $area, $setor);

        return response()->json(['ok' => true, 'setor' => $setor, 'ambiente' => Ambiente::paraPagina($dono, $area, $setor)]);
    }

    /** Áudio da área ou do setor (com suporte a Range, para o navegador avançar e voltar na música). */
    public function audio(Request $request, string $area): BinaryFileResponse
    {
        $caminho = Ambiente::caminhoAudio(Compartilhar::dono($request, $area), $area, $this->setor($request));
        abort_unless($caminho, 404);

        $tipos = ['mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'webm' => 'audio/webm'];

        return response()->file(Storage::disk('local')->path($caminho), [
            'Content-Type'  => $tipos[pathinfo($caminho, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /** Imagem ou GIF do fundo da área ou do setor. */
    public function imagem(Request $request, string $area): Response
    {
        $chave = Ambiente::chave($area, $this->setor($request)) . '.img';
        $valor = CorpoDado::where('user_id', Compartilhar::dono($request, $area))->where('chave', $chave)->value('valor');
        abort_if(! $valor, 404);
        [$tipo, $bin] = FundoController::decodificar($valor);

        return response($bin, 200, [
            'Content-Type'  => $tipo,
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
