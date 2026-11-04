<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail curto para conferir se o envio do site (o mesmo dos lembretes do calendário) está chegando. */
class TesteEnvio extends Mailable
{
    use Queueable;

    public function __construct(public string $nome, public string $quando)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Work Flower: e-mail de teste');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.teste-envio-texto');
    }
}
