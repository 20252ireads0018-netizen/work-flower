<?php

namespace App\Http\Controllers;

use App\Mail\LembreteEvento;
use App\Models\Evento;
use App\Support\Agendador;
use App\Support\Calendario;
use App\Support\EmBreve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Cartão "Lembretes por e-mail" do quadro (public/js/integracoes-cartoes.js): os próximos lembretes do calendário,
 * com o e-mail ligado ou não em cada um, e se o site está conseguindo mandar (servidor de e-mail + agendador).
 * Os eventos em si são criados e mudados pelo CalendarioController, como no calendário do painel.
 * O envio é do comando calendario:lembrar (routes/console.php), que no servidor depende do cron do cPanel
 * (passo a passo em docs/lembretes-por-email.md).
 */
class LembretesController extends Controller
{
    /** GET /lembretes/cartao[?dias=14] */
    public function cartao(Request $request): JsonResponse
    {
        $dias = max(1, min(60, (int) $request->query('dias', 14)));
        $agora = Calendario::agora();
        $de = $agora->copy()->subDays(2);
        $ate = $agora->copy()->addDays($dias);
        $lista = [];

        Evento::where('user_id', $request->user()->id)->whereNotNull('lembrete_min')->where('inicio', '<=', $ate)
            ->where(fn ($w) => $w->where('repete', '!=', 'nao')->orWhere('inicio', '>=', $de))
            ->orderBy('inicio')->limit(500)->get()
            ->each(function (Evento $e) use ($de, $ate, $agora, &$lista) {
                foreach (Calendario::ocorrencias($e, $de, $ate) as $o) {
                    if ($o->lt($agora->copy()->startOfDay())) {
                        continue;
                    }
                    $lista[] = [
                        'chave'  => $e->id . '@' . $o->format('YmdHi'),
                        'avisar' => Calendario::iso(Calendario::avisarEm($e, $o)),
                        'quando' => Calendario::iso($o),
                        'evento' => $e->paraJs($o),
                    ];
                    if (count($lista) > 300) {
                        return false;
                    }
                }
            });
        usort($lista, fn ($a, $b) => strcmp($a['quando'], $b['quando']));

        return response()->json(['lembretes' => array_slice($lista, 0, 120), 'envio' => self::envio(), 'email' => $request->user()->email]);
    }

    /** POST /lembretes/email { ligar }: liga ou desliga o e-mail em todos os eventos que têm lembrete. */
    public function todos(Request $request): JsonResponse
    {
        $d = $request->validate(['ligar' => ['required', 'boolean']]);
        $n = Evento::where('user_id', $request->user()->id)->whereNotNull('lembrete_min')
            ->where('lembrar_email', '!=', (bool) $d['ligar'])->update(['lembrar_email' => (bool) $d['ligar']]);

        return response()->json(['ok' => true, 'mudados' => $n]);
    }

    /** PATCH /lembretes/{evento} { lembrar_email } */
    public function email(Request $request, Evento $evento): JsonResponse
    {
        abort_unless((int) $evento->user_id === (int) $request->user()->id, 404);
        $d = $request->validate(['lembrar_email' => ['required', 'boolean']]);
        if ($evento->lembrete_min === null) {
            return response()->json(['message' => 'Este evento não tem lembrete.'], 422);
        }
        $evento->lembrar_email = (bool) $d['lembrar_email'];
        if ($evento->isDirty('lembrar_email') && $evento->lembrar_email) {
            $evento->email_enviado_para = null;
        }
        $evento->save();

        return response()->json(['ok' => true, 'evento' => $evento->paraJs()]);
    }

    /** POST /lembretes/teste: um lembrete de exemplo agora, para o e-mail da própria conta. */
    public function teste(Request $request): JsonResponse
    {
        $u = $request->user();
        if (! EmBreve::emailPronto()) {
            return response()->json(['message' => 'O envio de e-mails deste Work Flower ainda está sendo ligado. Por enquanto os lembretes aparecem só no navegador.'], 422);
        }
        $e = new Evento(['titulo' => 'Teste de lembrete do Work Flower', 'descricao' => 'Se este e-mail chegou, os lembretes do calendário vão chegar aqui também.', 'dia_todo' => false]);
        $e->inicio = Calendario::agora()->addHour();

        try {
            Mail::to($u->email, $u->name)->send(new LembreteEvento($e, $e->inicio->copy()));
        } catch (\Throwable $ex) {
            report($ex);

            return response()->json(['message' => 'O servidor de e-mail não aceitou o envio agora. Tente mais tarde.'], 422);
        }

        return response()->json(['ok' => true, 'msg' => "Lembrete de teste enviado para {$u->email}. Confira também a pasta de spam."]);
    }

    /** O site manda e-mail de verdade e o agendador rodou nos últimos 10 minutos? (sem detalhe técnico) */
    public static function envio(): array
    {
        $ultimo = Agendador::ultimo('calendario:lembrar');

        return [
            'email'       => EmBreve::emailPronto(),
            'agendador'   => $ultimo !== null && $ultimo->gt(now()->subMinutes(10)),
            'ultimo'      => $ultimo?->toIso8601String(),
        ];
    }
}
