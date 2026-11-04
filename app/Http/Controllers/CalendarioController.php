<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Tarefa;
use App\Support\Calendario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Calendário do painel (public/js/calendario.js) e lembretes no navegador (public/js/lembretes.js).
 * Cada pessoa vê só os próprios eventos; as tarefas com prazo aparecem junto, só para consulta.
 */
class CalendarioController extends Controller
{
    /** Eventos guardados por conta. */
    public const MAX_EVENTOS = 2000;

    /** Opções de lembrete (minutos antes). */
    public const LEMBRETES = [0, 5, 10, 15, 30, 60, 120, 1440, 2880, 10080];

    /** Ocorrências e tarefas com prazo entre ?de=Y-m-d e ?ate=Y-m-d (até 400 dias). */
    public function lista(Request $request): JsonResponse
    {
        $q = $request->validate([
            'de'  => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
        ]);
        $de = Carbon::parse($q['de'])->startOfDay();
        $ate = Carbon::parse($q['ate'])->endOfDay();
        if ($de->diffInDays($ate) > 400) {
            return response()->json(['message' => 'Período longo demais.'], 422);
        }

        $uid = $request->user()->id;
        $eventos = [];
        Evento::where('user_id', $uid)->where('inicio', '<=', $ate)
            ->where(fn ($w) => $w->where('repete', '!=', 'nao')->orWhere('inicio', '>=', $de))
            ->orderBy('inicio')->get()
            ->each(function (Evento $e) use ($de, $ate, &$eventos) {
                foreach (Calendario::ocorrencias($e, $de, $ate) as $o) {
                    $eventos[] = $e->paraJs($o);
                }
            });
        usort($eventos, fn ($a, $b) => strcmp($a['ocorrencia'], $b['ocorrencia']));

        $tarefas = Tarefa::where('user_id', $uid)->whereNotNull('prazo')
            ->whereBetween('prazo', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('prazo')->limit(500)->get()
            ->map(fn (Tarefa $t) => [
                'id'     => $t->id,
                'titulo' => $t->titulo,
                'area'   => Tarefa::AREAS[$t->area]['nome'] ?? $t->area,
                'prazo'  => $t->prazo->format('Y-m-d'),
                'feita'  => $t->status === 'concluida',
                'url'    => isset(Tarefa::AREAS[$t->area]) ? route('areas.show', $t->area) : null,
            ]);

        return response()->json(['eventos' => $eventos, 'tarefas' => $tarefas]);
    }

    public function criar(Request $request): JsonResponse
    {
        if (Evento::where('user_id', $request->user()->id)->count() >= self::MAX_EVENTOS) {
            return response()->json(['message' => 'Limite de ' . self::MAX_EVENTOS . ' eventos. Apague alguns antigos para criar outros.'], 422);
        }

        $e = new Evento($this->validar($request));
        $e->user_id = $request->user()->id;
        $e->save();

        return response()->json(['ok' => true, 'evento' => $e->paraJs()]);
    }

    public function atualizar(Request $request, Evento $evento): JsonResponse
    {
        abort_unless($evento->user_id === $request->user()->id, 404);

        $evento->fill($this->validar($request));
        // mudou a data ou o lembrete: as próximas ocorrências voltam a receber e-mail
        if ($evento->isDirty(['inicio', 'repete', 'lembrete_min', 'lembrar_email', 'dia_todo'])) {
            $evento->email_enviado_para = null;
        }
        $evento->save();

        return response()->json(['ok' => true, 'evento' => $evento->paraJs()]);
    }

    public function apagar(Request $request, Evento $evento): JsonResponse
    {
        abort_unless($evento->user_id === $request->user()->id, 404);
        $evento->delete();

        return response()->json(['ok' => true]);
    }

    /** Lembretes das próximas 36 horas (e dos últimos 10 minutos), para o navegador avisar. */
    public function lembretes(Request $request): JsonResponse
    {
        $agora = Calendario::agora();
        $de = $agora->copy()->subDays(2);  // eventos de dia inteiro e lembretes de até 1 semana antes
        $ate = $agora->copy()->addDays(8);
        $lista = [];

        Evento::where('user_id', $request->user()->id)->whereNotNull('lembrete_min')->where('inicio', '<=', $ate)
            ->where(fn ($w) => $w->where('repete', '!=', 'nao')->orWhere('inicio', '>=', $de))
            ->limit(500)->get()
            ->each(function (Evento $e) use ($de, $ate, $agora, &$lista) {
                foreach (Calendario::ocorrencias($e, $de, $ate) as $o) {
                    $avisar = Calendario::avisarEm($e, $o);
                    if ($avisar->between($agora->copy()->subMinutes(10), $agora->copy()->addHours(36))) {
                        $lista[] = [
                            'chave'    => $e->id . '@' . $o->format('YmdHi'),
                            'titulo'   => $e->titulo,
                            'dia_todo' => $e->dia_todo,
                            'quando'   => Calendario::iso($o),
                            'avisar'   => Calendario::iso($avisar),
                        ];
                    }
                }
            });
        usort($lista, fn ($a, $b) => strcmp($a['avisar'], $b['avisar']));

        return response()->json(['lembretes' => array_slice($lista, 0, 100)]);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'titulo'        => ['required', 'string', 'max:120'],
            'descricao'     => ['nullable', 'string', 'max:2000'],
            'inicio'        => ['required', 'date_format:Y-m-d\TH:i'],
            'fim'           => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:inicio'],
            'dia_todo'      => ['boolean'],
            'cor'           => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'repete'        => ['required', Rule::in(array_keys(Evento::REPETE))],
            'repete_ate'    => ['nullable', 'date_format:Y-m-d'],
            'lembrete_min'  => ['nullable', 'integer', Rule::in(self::LEMBRETES)],
            'lembrar_email' => ['boolean'],
        ], [], ['inicio' => 'início', 'fim' => 'fim', 'repete_ate' => 'repetir até']);

        $diaTodo = (bool) ($d['dia_todo'] ?? false);
        $inicio = Carbon::createFromFormat('Y-m-d\TH:i', $d['inicio']);
        $fim = empty($d['fim']) ? null : Carbon::createFromFormat('Y-m-d\TH:i', $d['fim']);
        if ($diaTodo) {
            $inicio->startOfDay();
            $fim = $fim && $fim->toDateString() !== $inicio->toDateString() ? $fim->startOfDay() : null;
        }

        return [
            'titulo'        => trim($d['titulo']),
            'descricao'     => trim((string) ($d['descricao'] ?? '')) ?: null,
            'inicio'        => $inicio,
            'fim'           => $fim,
            'dia_todo'      => $diaTodo,
            'cor'           => strtolower($d['cor'] ?? '#2f6fc7'),
            'repete'        => $d['repete'],
            'repete_ate'    => $d['repete'] === 'nao' || empty($d['repete_ate']) ? null : $d['repete_ate'],
            'lembrete_min'  => $d['lembrete_min'] ?? null,
            'lembrar_email' => isset($d['lembrete_min']) && (bool) ($d['lembrar_email'] ?? false),
        ];
    }
}
