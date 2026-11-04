<?php

namespace App\Mail;

use App\Models\Evento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/** E-mail de lembrete de um evento do calendário (comando calendario:lembrar). */
class LembreteEvento extends Mailable
{
    use Queueable;

    public function __construct(public Evento $evento, public Carbon $ocorrencia)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lembrete: ' . $this->evento->titulo);
    }

    public function content(): Content
    {
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $o = $this->ocorrencia;
        $quando = $dias[$o->dayOfWeek] . ', ' . $o->format('d/m/Y') . ($this->evento->dia_todo ? ' (dia todo)' : ' às ' . $o->format('H:i'));

        return new Content(
            view: 'emails.lembrete-evento',
            text: 'emails.lembrete-evento-texto',
            with: ['quando' => $quando, 'link' => route('painel')],
        );
    }
}
