<?php

namespace App\Console\Commands;

use App\Mail\LembreteEvento;
use App\Models\Evento;
use App\Support\Calendario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Manda por e-mail os lembretes do calendário que chegaram na hora. Roda a cada minuto pelo agendador
 * (routes/console.php), que no servidor depende do cron "php artisan schedule:run" no cPanel.
 * Se o cron ficar parado, lembretes com mais de 30 minutos de atraso não são mandados.
 */
class LembrarEventos extends Command
{
    protected $signature = 'calendario:lembrar';
    protected $description = 'Envia por e-mail os lembretes dos eventos do calendário que chegaram na hora.';

    private const ATRASO_MAX = 30; // minutos

    public function handle(): int
    {
        $agora = Calendario::agora();
        $de = $agora->copy()->subDays(2);
        $ate = $agora->copy()->addDays(8);
        $n = 0;

        Evento::with('user:id,name,email')
            ->where('lembrar_email', true)->whereNotNull('lembrete_min')->where('inicio', '<=', $ate)
            ->where(fn ($w) => $w->where('repete', '!=', 'nao')->orWhere('inicio', '>=', $de))
            ->chunkById(200, function ($eventos) use ($agora, $de, $ate, &$n) {
                foreach ($eventos as $e) {
                    $devida = null;
                    foreach (Calendario::ocorrencias($e, $de, $ate) as $o) {
                        $avisar = Calendario::avisarEm($e, $o);
                        if ($avisar->gt($agora)) {
                            break;
                        }
                        if ($avisar->gte($agora->copy()->subMinutes(self::ATRASO_MAX))
                            && (! $e->email_enviado_para || $o->gt($e->email_enviado_para))) {
                            $devida = $o;
                        }
                    }
                    if (! $devida || ! $e->user?->email) {
                        continue;
                    }

                    try {
                        Mail::to($e->user->email, $e->user->name)->send(new LembreteEvento($e, $devida));
                        $n++;
                    } catch (\Throwable $ex) {
                        report($ex);
                    }
                    // marca mesmo se falhar: um servidor de e-mail com problema não pode gerar um envio por minuto
                    $e->forceFill(['email_enviado_para' => $devida])->saveQuietly();
                }
            });

        $this->info("{$n} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }
}
