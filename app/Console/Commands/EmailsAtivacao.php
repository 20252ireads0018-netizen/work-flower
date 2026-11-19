<?php

namespace App\Console\Commands;

use App\Mail\AtivacaoEmail;
use App\Models\User;
use App\Support\Agendador;
use App\Support\Ativacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Sequência de ativação por e-mail (App\Support\Ativacao): roda de hora em hora pelo agendador. */
class EmailsAtivacao extends Command
{
    protected $signature = 'emails:ativacao';
    protected $description = 'Envia os e-mails de ativação (dias 0, 1, 3 e 7) para quem se cadastrou.';

    public function handle(): int
    {
        Agendador::marcar('emails:ativacao');
        $janela = max(array_map(fn ($e) => $e[1], Ativacao::ETAPAS));
        $enviados = 0;

        User::where('created_at', '>=', max(now()->subHours($janela), \Illuminate\Support\Carbon::parse(Ativacao::INICIO)))
            ->orderBy('id')->chunkById(200, function ($usuarios) use (&$enviados) {
                foreach ($usuarios as $u) {
                    $etapa = Ativacao::devida($u);
                    if (! $etapa) continue;
                    try {
                        Mail::to($u->email)->send(new AtivacaoEmail($u, $etapa, $etapa === 'd3' && Ativacao::usouRevisao($u->id)));
                        Ativacao::marcar($u->id, $etapa);
                        $enviados++;
                    } catch (\Throwable $e) {
                        // sem SMTP ou endereço recusado: tenta de novo na próxima hora (dentro da janela da etapa)
                        Log::warning('emails:ativacao falhou para o usuário ' . $u->id . ': ' . $e->getMessage());
                    }
                }
            });

        $this->info("E-mails de ativação enviados: {$enviados}");

        return self::SUCCESS;
    }
}
