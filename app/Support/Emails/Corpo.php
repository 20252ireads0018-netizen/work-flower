<?php

namespace App\Support\Emails;

use App\Models\EmailConta;
use App\Models\EmailMensagem;
use Illuminate\Support\Facades\Http;

/**
 * Corpo inteiro de uma mensagem já sincronizada, lido na hora (o banco só guarda o trecho). Usado pelo cartão
 * Gmail do quadro para abrir a mensagem e para virar cartão de texto. Nada é marcado como lido.
 */
class Corpo
{
    public const MAX = 20000;

    public static function ler(EmailMensagem $m): string
    {
        $conta = $m->conta;
        if (! $conta) {
            throw new EmailErro('A caixa desta mensagem foi removida.');
        }

        return $conta->tipo === 'gmail' ? self::gmail($conta, (string) $m->uid) : self::imap($conta, (string) $m->uid);
    }

    private static function gmail(EmailConta $conta, string $id): string
    {
        $r = Http::withToken(Google::token($conta))->timeout(20)->get(Google::API . '/messages/' . rawurlencode($id), ['format' => 'full']);
        if ($r->status() === 404) {
            throw new EmailErro('A mensagem não está mais no Gmail.');
        }
        if (! $r->successful()) {
            throw new EmailErro('O Gmail recusou abrir a mensagem (' . $r->status() . ').');
        }

        $html = null;
        $texto = self::parteGmail((array) $r->json('payload', []), $html);

        return $texto !== null && trim($texto) !== ''
            ? Mime::texto('text/plain; charset=utf-8', '', $texto, self::MAX)
            : Mime::texto('text/html; charset=utf-8', '', (string) $html, self::MAX);
    }

    /** Primeiro text/plain da árvore de partes; guarda o primeiro text/html em $html. */
    private static function parteGmail(array $p, ?string &$html, int $nivel = 0): ?string
    {
        $mime = strtolower((string) ($p['mimeType'] ?? ''));
        $dados = (string) ($p['body']['data'] ?? '');
        if ($dados !== '' && ! ($p['filename'] ?? '')) {
            $bruto = (string) base64_decode(strtr($dados, '-_', '+/'));
            if ($mime === 'text/plain') {
                return $bruto;
            }
            if ($mime === 'text/html') {
                $html ??= $bruto;
            }
        }
        if ($nivel < 5) {
            foreach ((array) ($p['parts'] ?? []) as $sub) {
                $t = self::parteGmail((array) $sub, $html, $nivel + 1);
                if ($t !== null) {
                    return $t;
                }
            }
        }

        return null;
    }

    private static function imap(EmailConta $conta, string $uid): string
    {
        if (! ctype_digit($uid)) {
            throw new EmailErro('Mensagem inválida.');
        }
        $imap = new ImapCliente();
        $imap->conectar((string) $conta->host, (int) ($conta->porta ?: 993), $conta->seguranca ?: 'ssl');
        try {
            $imap->entrar((string) ($conta->usuario ?: $conta->endereco), (string) $conta->senha);
            $imap->examinar($conta->pasta ?: 'INBOX');
            $m = $imap->cabecalhos([(int) $uid], 400000)[0] ?? null;
        } finally {
            $imap->sair();
        }
        if (! $m) {
            throw new EmailErro('A mensagem não está mais na caixa.');
        }
        $cab = Mime::cabecalhos($m['cabecalho']);

        return Mime::texto($cab['content-type'] ?? 'text/plain', $cab['content-transfer-encoding'] ?? '', $m['corpo'], self::MAX);
    }
}
