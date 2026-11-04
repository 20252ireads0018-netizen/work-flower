<?php

namespace App\Console\Commands;

use App\Mail\LembreteEvento;
use App\Models\DiscordIntegracao;
use App\Models\Evento;
use App\Support\Calendario;
use App\Support\Discord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Manda por e-mail (e para o Discord de quem ligou a integração) os lembretes do calendário que chegaram na hora.
 * Roda a cada minuto pelo agendador (routes/console.php), que no servidor depende do cron
 * "php artisan schedule:run" no cPanel. Se o cron ficar parado, lembretes com mais de 30 minutos de atraso
 * não são mandados.
 */
class LembrarEventos extends Command
{
    protected $signature = 'calendario:lembrar';
    protected $description = 'Envia por e-mail e Discord os lembretes dos eventos do calendário que chegaram na hora.';

    private const ATRASO_MAX = 30; // minutos

    public function handle(): int
    {
        $agora = Calendario::agora();
        $de = $agora->copy()->subDays(2);
        $ate = $agora->copy()->addDays(8);
        $n = 0;
        $d = 0;

        // contas com o Discord ligado e o aviso de lembretes ativo recebem lá mesmo sem "Avisar por e-mail"
        $comDiscord = DiscordIntegracao::whereNotNull('webhook_url')->get(['user_id', 'eventos'])
            ->filter(fn (DiscordIntegracao $i) => $i->avisa('lembrete'))->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        Evento::with('user:id,name,email')
            ->whereNotNull('lembrete_min')->where('inicio', '<=', $ate)
            ->where(fn ($w) => $w->where('lembrar_email', true)->orWhereIn('user_id', $comDiscord ?: [0]))
            ->where(fn ($w) => $w->where('repete', '!=', 'nao')->orWhere('inicio', '>=', $de))
            ->chunkById(200, function ($eventos) use ($agora, $de, $ate, $comDiscord, &$n, &$d) {
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
                    if (! $devida) {
                        continue;
                    }

                    if ($e->lembrar_email && $e->user?->email) {
                        try {
                            Mail::to($e->user->email, $e->user->name)->send(new LembreteEvento($e, $devida));
                            $n++;
                        } catch (\Throwable $ex) {
                            report($ex);
                        }
                    }
                    if (in_array((int) $e->user_id, $comDiscord, true)) {
                        $quando = $devida->format('d/m/Y') . ($e->dia_todo ? ' (dia todo)' : ' às ' . $devida->format('H:i'));
                        if (Discord::avisar((int) $e->user_id, 'lembrete', '⏰ Lembrete: ' . $e->titulo, (string) $e->descricao, route('painel'), ['Quando' => $quando])) {
                            $d++;
                        }
                    }
                    // marca mesmo se falhar: um servidor com problema não pode gerar um envio por minuto
                    $e->forceFill(['email_enviado_para' => $devida])->saveQuietly();
                }
            });

        \App\Support\Agendador::marcar($this->getName()); // mostra em Negócios › E-mails se o cron está rodando
        $this->info("{$n} lembrete(s) por e-mail e {$d} no Discord.");

        return self::SUCCESS;
    }
}
