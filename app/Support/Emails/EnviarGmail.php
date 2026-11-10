<?php

namespace App\Support\Emails;

use App\Models\EmailConta;
use App\Models\EmailMensagem;
use Illuminate\Support\Facades\Http;

/**
 * Manda um e-mail pela Gmail API (escopo gmail.send) a partir do cartão Gmail do quadro: novo, resposta (na mesma
 * conversa do Gmail, com In-Reply-To/References) ou encaminhamento (o texto da mensagem original vai citado; anexos
 * não vão). Só texto simples. Cabeçalhos limpos de quebras de linha para ninguém injetar destinatários.
 */
class EnviarGmail
{
    /** Erro do Google quando o token não tem o gmail.send (a pessoa ainda não permitiu ou tirou a permissão). */
    public const SEM_PERMISSAO = 'sem_permissao';

    /**
     * @param  list<string>  $para
     * @param  list<string>  $cc
     * @return string id da mensagem enviada no Gmail
     */
    public static function enviar(EmailConta $conta, array $para, array $cc, string $assunto, string $texto, string $modo = 'novo', ?EmailMensagem $original = null): string
    {
        if (! Google::podeEnviar($conta)) {
            throw new EmailErro(self::SEM_PERMISSAO);
        }
        $token = Google::token($conta);

        $cab = [];
        $thread = null;
        if ($original && $modo !== 'novo') {
            $h = [];
            if ((int) $original->conta_id === (int) $conta->id) {
                // cabeçalhos da original (gmail.readonly) e a conversa, para a resposta cair no mesmo fio do Gmail.
                // Parâmetro repetido (metadataHeaders=a&metadataHeaders=b), como a API pede: montado à mão.
                $r = Http::withToken($token)->timeout(20)->get(Google::API . '/messages/' . rawurlencode((string) $original->uid)
                    . '?format=metadata&metadataHeaders=Message-ID&metadataHeaders=References');
                if ($r->successful()) {
                    foreach ((array) $r->json('payload.headers', []) as $x) {
                        $h[strtolower((string) ($x['name'] ?? ''))] = self::limpar((string) ($x['value'] ?? ''));
                    }
                    if ($modo === 'responder') $thread = $r->json('threadId');
                }
            }
            // e-mail de uma caixa IMAP respondido pelo Gmail: o Message-ID guardado na sincronização basta
            $mid = $h['message-id'] ?? self::limpar((string) $original->message_id);
            if ($modo === 'responder' && $mid !== '') {
                $cab['In-Reply-To'] = $mid;
                $cab['References'] = trim(($h['references'] ?? '') . ' ' . $mid);
            }
            if ($modo === 'encaminhar') {
                try {
                    $textoOriginal = Corpo::ler($original);
                } catch (EmailErro) {
                    $textoOriginal = (string) $original->trecho; // a original sumiu da caixa: vai o trecho guardado
                }
                $texto = rtrim($texto) . "\n\n---------- Mensagem encaminhada ----------\n"
                    . 'De: ' . trim(($original->de_nome ? $original->de_nome . ' ' : '') . '<' . $original->de_email . '>') . "\n"
                    . 'Data: ' . ($original->recebido_em?->format('d/m/Y H:i') ?? '') . "\n"
                    . 'Assunto: ' . $original->assunto . "\n\n"
                    . $textoOriginal;
            }
        }

        $raw = self::mime($conta->endereco, $para, $cc, $assunto, $texto, $cab);
        $r = Http::withToken($token)->timeout(30)->acceptJson()->post(Google::API . '/messages/send', array_filter([
            'raw'      => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            'threadId' => $thread,
        ]));
        if ($r->status() === 403 && str_contains(strtolower((string) $r->body()), 'scope')) {
            // o Google tirou o envio (a pessoa revogou na conta Google): volta a pedir
            $conta->forceFill(['escopos' => trim(str_replace(Google::ESCOPO_ENVIAR, '', (string) $conta->escopos))])->save();
            throw new EmailErro(self::SEM_PERMISSAO);
        }
        if (! $r->successful()) {
            $motivo = (string) ($r->json('error.message') ?: 'HTTP ' . $r->status());
            throw new EmailErro('O Gmail recusou o envio (' . mb_substr($motivo, 0, 140) . ').');
        }

        return (string) $r->json('id');
    }

    /** Mensagem RFC 5322 em texto simples UTF-8 (corpo em base64). */
    public static function mime(string $de, array $para, array $cc, string $assunto, string $texto, array $extra = []): string
    {
        $linhas = [
            'From: ' . self::limpar($de),
            'To: ' . implode(', ', array_map([self::class, 'limpar'], $para)),
        ];
        if ($cc) $linhas[] = 'Cc: ' . implode(', ', array_map([self::class, 'limpar'], $cc));
        $linhas[] = 'Subject: ' . self::cabecalhoUtf8(self::limpar($assunto));
        foreach ($extra as $k => $v) {
            $linhas[] = $k . ': ' . self::limpar($v);
        }
        $linhas[] = 'MIME-Version: 1.0';
        $linhas[] = 'Content-Type: text/plain; charset=UTF-8';
        $linhas[] = 'Content-Transfer-Encoding: base64';

        $corpo = str_replace(["\r\n", "\r"], "\n", $texto);

        return implode("\r\n", $linhas) . "\r\n\r\n" . rtrim(chunk_split(base64_encode(str_replace("\n", "\r\n", $corpo)), 76, "\r\n"));
    }

    private static function cabecalhoUtf8(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function limpar(string $s): string
    {
        return trim(preg_replace('/[\r\n\x00]+/', ' ', $s));
    }
}
