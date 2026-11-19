<?php

namespace App\Mail;

use App\Models\User;
use App\Support\Ativacao;
use App\Support\Planos;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/** E-mails da sequência de ativação (App\Support\Ativacao): d0, d1, d3 e d7, sempre com o link de descadastro. */
class AtivacaoEmail extends Mailable
{
    use Queueable;

    public string $sair;

    public function __construct(public User $usuario, public string $etapa, public bool $usou = false)
    {
        $this->sair = URL::signedRoute('emails.sair', ['user' => $usuario->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->etapa) {
            'd0' => 'Bem-vindo ao Work Flower: sua primeira revisão em 1 minuto',
            'd1' => 'Como o Work Flower avisa a hora de revisar cada matéria',
            'd3' => $this->usou ? 'Próximo passo: prazos e calendário num lugar só' : 'Sua primeira revisão ainda está esperando',
            default => 'Seu Work Flower depois do teste grátis',
        });
    }

    /** Descadastro em um clique pelo leitor de e-mail (Gmail, Outlook…). */
    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<' . $this->sair . '>']);
    }

    public function content(): Content
    {
        $estudos = url('/areas/mente?aba=estudos');
        $teste = Planos::testeAte($this->usuario);

        return new Content(
            view: 'emails.ativacao',
            text: 'emails.ativacao-texto',
            with: [
                'nome'     => trim(strtok((string) $this->usuario->name, ' ')) ?: 'tudo bem',
                'estudos'  => $estudos,
                'calendario' => route('painel'),
                'planos'   => route('planos'),
                'preco'    => Ativacao::preco('basico'),
                'testeAte' => $teste?->format('d/m/Y'),
            ],
        );
    }
}
