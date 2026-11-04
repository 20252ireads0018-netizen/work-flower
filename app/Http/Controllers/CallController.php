<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Evento;
use App\Models\Tarefa;
use App\Models\User;
use App\Support\Compartilhar;
use App\Support\Discord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Negócios › Calls: marca uma reunião com link (Google Meet, Zoom, canal de voz do Discord ou sala nova do Jitsi)
 * e as pessoas da equipe. Cada pessoa (e quem marcou) ganha o evento no calendário do painel, com lembrete no
 * navegador e por e-mail (comando calendario:lembrar), e o Discord de quem marcou recebe o aviso.
 */
class CallController extends Controller
{
    use Concerns\CorpoCodificado;

    private const MAX_CALLS = 1000;

    private const COR = '#7c3aed';

    /** Pessoas que podem entrar nas calls de $donoId: ele e quem está em algum dos workflows dele. [id => nome] */
    public static function pessoas(int $donoId): array
    {
        $todas = [];
        foreach (array_keys(Tarefa::AREAS) as $area) {
            $todas += Compartilhar::pessoas($donoId, $area);
        }

        return $todas;
    }

    public function index(Request $request): JsonResponse
    {
        $eu = $request->user()->id;
        $desde = now()->subDays(30);
        $minhas = Call::with('user:id,name')->where('user_id', $eu)->where('inicio', '>=', $desde)->orderBy('inicio')->limit(300)->get();
        // calls de outras pessoas em que estou: vêm pelos eventos que ganhei
        $ids = Evento::where('user_id', $eu)->whereNotNull('call_id')->pluck('call_id');
        $convites = Call::with('user:id,name')->whereIn('id', $ids)->where('user_id', '!=', $eu)->where('inicio', '>=', $desde)->orderBy('inicio')->limit(300)->get();

        $nomes = self::pessoas($eu);
        $outros = $minhas->concat($convites)->pluck('participantes')->flatten()->map(fn ($id) => (int) $id)->diff(array_keys($nomes))->unique();
        if ($outros->isNotEmpty()) {
            $nomes += User::whereIn('id', $outros)->pluck('name', 'id')->all();
        }

        return response()->json([
            'calls'       => $minhas->concat($convites)->sortBy('inicio')->values()->map(fn (Call $c) => $c->paraJs($eu)),
            'pessoas'     => collect(self::pessoas($eu))->map(fn ($n, $id) => ['id' => (int) $id, 'nome' => $n])->values(),
            'nomes'       => $nomes,
            'plataformas' => Call::PLATAFORMAS,
            'lembretes'   => CalendarioController::LEMBRETES,
            'eu'          => $eu,
        ]);
    }

    public function criar(Request $request): JsonResponse
    {
        $eu = $request->user()->id;
        if (Call::where('user_id', $eu)->count() >= self::MAX_CALLS) {
            return response()->json(['message' => 'Limite de ' . self::MAX_CALLS . ' calls. Apague algumas antigas.'], 422);
        }
        $call = new Call($this->validar($request));
        $call->user_id = $eu;
        DB::transaction(function () use ($call) {
            $call->save();
            $this->sincronizarEventos($call);
        });

        $this->avisarDiscord($call, '📞 Call marcada');

        return response()->json(['ok' => true, 'call' => $call->load('user:id,name')->paraJs($eu)]);
    }

    public function atualizar(Request $request, Call $call): JsonResponse
    {
        abort_unless($call->user_id === $request->user()->id, 404);
        $call->fill($this->validar($request));
        $mudouHora = $call->isDirty(['inicio', 'link']);
        DB::transaction(function () use ($call) {
            $call->save();
            $this->sincronizarEventos($call);
        });
        if ($mudouHora) {
            $this->avisarDiscord($call, '📞 Call remarcada');
        }

        return response()->json(['ok' => true, 'call' => $call->load('user:id,name')->paraJs($request->user()->id)]);
    }

    public function apagar(Request $request, Call $call): JsonResponse
    {
        abort_unless($call->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($call) {
            Evento::where('call_id', $call->id)->delete();
            $call->delete();
        });
        $this->avisarDiscord($call, '❌ Call cancelada', false);

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        $this->decodificarCorpo($request);
        $eu = $request->user()->id;
        $d = $request->validate([
            'titulo'          => ['required', 'string', 'max:110'],
            'descricao'       => ['nullable', 'string', 'max:1500'],
            'inicio'          => ['required', 'date_format:Y-m-d\TH:i'],
            'duracao_min'     => ['required', 'integer', 'min:5', 'max:720'],
            'plataforma'      => ['required', Rule::in(array_keys(Call::PLATAFORMAS))],
            'link'            => ['nullable', 'string', 'max:500'],
            'participantes'   => ['nullable', 'array', 'max:50'],
            'participantes.*' => ['integer', Rule::in(array_keys(self::pessoas($eu)))],
            'lembrete_min'    => ['nullable', 'integer', Rule::in(CalendarioController::LEMBRETES)],
        ], [
            'titulo.required'    => 'Dê um nome à call.',
            'inicio.required'    => 'Escolha o dia e a hora.',
            'participantes.*.in' => 'Uma das pessoas não está na sua equipe.',
        ]);

        $link = trim((string) ($d['link'] ?? ''));
        if ($link === '' && $d['plataforma'] === 'jitsi') {
            $link = Call::linkJitsi();
        }
        if ($link === '' || ! filter_var($link, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($link), 'https://')) {
            throw ValidationException::withMessages(['link' => 'Cole o link da call (começando com https://).']);
        }
        $d['link'] = $link;
        // o link manda: um link do Zoom marcado como "Meet" vira Zoom
        $detectada = Call::plataformaDe($link);
        if ($detectada !== 'outro') {
            $d['plataforma'] = $detectada;
        }
        $d['participantes'] = array_values(array_unique(array_map('intval', array_diff($d['participantes'] ?? [], [$eu]))));
        $d['descricao'] = trim((string) ($d['descricao'] ?? '')) ?: null;

        return $d;
    }

    /** Um evento por pessoa (quem marcou + participantes); quem saiu da call perde o evento. */
    private function sincronizarEventos(Call $call): void
    {
        $pessoas = array_merge([$call->user_id], $call->participantes ?? []);
        $nomes = self::pessoas($call->user_id);
        $com = collect($pessoas)->map(fn ($id) => $nomes[$id] ?? null)->filter()->implode(', ');
        $inicio = Carbon::parse($call->inicio);

        $descricao = 'Link da call (' . (Call::PLATAFORMAS[$call->plataforma] ?? 'link') . '): ' . $call->link
            . ($com ? "\nCom: {$com}" : '')
            . ($call->descricao ? "\n\n" . $call->descricao : '');

        Evento::where('call_id', $call->id)->whereNotIn('user_id', $pessoas)->delete();
        foreach ($pessoas as $uid) {
            $e = Evento::where(['call_id' => $call->id, 'user_id' => $uid])->first() ?? new Evento();
            $e->fill([
                'titulo'        => mb_substr('📞 ' . $call->titulo, 0, 120),
                'descricao'     => mb_substr($descricao, 0, 2000),
                'inicio'        => $inicio,
                'fim'           => $inicio->copy()->addMinutes($call->duracao_min),
                'dia_todo'      => false,
                'cor'           => self::COR,
                'repete'        => 'nao',
                'repete_ate'    => null,
                'lembrete_min'  => $call->lembrete_min,
                'lembrar_email' => $call->lembrete_min !== null,
            ]);
            $e->forceFill(['user_id' => $uid, 'call_id' => $call->id]);
            if ($e->isDirty(['inicio', 'lembrete_min', 'lembrar_email'])) {
                $e->email_enviado_para = null;
            }
            $e->save();
        }
    }

    private function avisarDiscord(Call $call, string $titulo, bool $comLink = true): void
    {
        $nomes = self::pessoas($call->user_id);
        Discord::avisar($call->user_id, 'call', $titulo . ': ' . $call->titulo, (string) $call->descricao, $comLink ? $call->link : null, [
            'Quando'       => $call->inicio->format('d/m/Y \à\s H:i') . ' (' . $call->duracao_min . ' min)',
            'Onde'         => Call::PLATAFORMAS[$call->plataforma] ?? null,
            'Link'         => $comLink ? $call->link : null,
            'Participantes' => collect($call->participantes ?? [])->map(fn ($id) => $nomes[$id] ?? null)->filter()->implode(', ') ?: null,
        ]);
    }
}
