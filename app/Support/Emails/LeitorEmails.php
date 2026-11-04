<?php

namespace App\Support\Emails;

use App\Models\EmailConta;

/**
 * Quem lê a caixa de entrada. A implementação real (LeitorPadrao) usa IMAP ou a API do Gmail;
 * os testes trocam por uma falsa com app()->instance(LeitorEmails::class, ...).
 */
#[\Illuminate\Container\Attributes\Bind(LeitorPadrao::class)]
interface LeitorEmails
{
    /** Confere se dá para entrar na caixa. Lança EmailErro com o motivo, em português. */
    public function testar(EmailConta $conta): void;

    /**
     * Lê as mensagens dos últimos $dias.
     *
     * @param  list<string>  $conhecidos  UIDs já guardados (deles só volta o lido/não lido)
     * @return array{uidvalidity: ?string, novas: list<array{uid: string, message_id: ?string, de_nome: ?string, de_email: ?string, assunto: ?string, trecho: ?string, recebido_em: ?\DateTimeInterface, lido: bool}>, lidos: array<string, bool>}
     */
    public function ler(EmailConta $conta, array $conhecidos, int $dias, int $limite): array;
}
