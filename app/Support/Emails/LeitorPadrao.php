<?php

namespace App\Support\Emails;

use App\Models\EmailConta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/** Leitor de verdade: IMAP (Titan, Gmail com senha de app, cPanel...) ou Gmail API quando a conta veio pelo Google. */
class LeitorPadrao implements LeitorEmails
{
    public function testar(EmailConta $conta): void
    {
        if ($conta->tipo === 'gmail') {
            $r = Http::withToken(Google::token($conta))->timeout(15)->get(Google::API . '/profile');
            if (! $r->successful()) throw new EmailErro('O Gmail recusou o acesso. Conecte a conta de novo.');

            return;
        }
        $imap = $this->abrir($conta);
        try {
            $imap->examinar($conta->pasta ?: 'INBOX');
        } finally {
            $imap->sair();
        }
    }

    public function ler(EmailConta $conta, array $conhecidos, int $dias, int $limite): array
    {
        return $conta->tipo === 'gmail'
            ? $this->lerGmail($conta, $conhecidos, $dias, $limite)
            : $this->lerImap($conta, $conhecidos, $dias, $limite);
    }

    /* ---------------- IMAP ---------------- */

    private function abrir(EmailConta $conta): ImapCliente
    {
        $imap = new ImapCliente();
        $imap->conectar((string) $conta->host, (int) ($conta->porta ?: 993), $conta->seguranca ?: 'ssl');
        try {
            $imap->entrar((string) ($conta->usuario ?: $conta->endereco), (string) $conta->senha);
        } catch (EmailErro $e) {
            $imap->fechar();
            throw $e;
        }

        return $imap;
    }

    private function lerImap(EmailConta $conta, array $conhecidos, int $dias, int $limite): array
    {
        $imap = $this->abrir($conta);
        try {
            $validade = $imap->examinar($conta->pasta ?: 'INBOX');
            $todos = $imap->buscarDesde(now()->subDays($dias));
            // se a pasta foi recriada no servidor, os UIDs antigos não valem mais
            $mesmaPasta = ! $conta->uidvalidity || $conta->uidvalidity === $validade;
            $conhecidosSet = $mesmaPasta ? array_flip($conhecidos) : [];

            $novos = array_values(array_filter($todos, fn ($u) => ! isset($conhecidosSet[(string) $u])));
            $novos = array_slice($novos, -$limite); // os mais recentes
            $velhos = array_values(array_filter($todos, fn ($u) => isset($conhecidosSet[(string) $u])));

            $lidos = [];
            foreach ($velhos ? $imap->lidos($velhos) : [] as $uid => $lido) $lidos[(string) $uid] = $lido;

            $mensagens = [];
            foreach ($novos ? $imap->cabecalhos($novos) : [] as $m) {
                $cab = Mime::cabecalhos($m['cabecalho']);
                [$nome, $email] = Mime::remetente($cab['from'] ?? '');
                $data = null;
                foreach ([$m['data'], $cab['date'] ?? null] as $d) {
                    if (! $d) continue;
                    try {
                        $data = Carbon::parse(preg_replace('/\s*\(.*\)$/', '', $d));
                        break;
                    } catch (\Throwable) {
                    }
                }
                $mensagens[] = [
                    'uid'         => (string) $m['uid'],
                    'message_id'  => mb_substr(trim($cab['message-id'] ?? '', '<> '), 0, 250) ?: null,
                    'de_nome'     => $nome ?: null,
                    'de_email'    => $email ?: null,
                    'assunto'     => mb_substr(Mime::decodificar($cab['subject'] ?? ''), 0, 300) ?: null,
                    'trecho'      => Mime::trecho($cab['content-type'] ?? 'text/plain', $cab['content-transfer-encoding'] ?? '', $m['corpo']) ?: null,
                    'recebido_em' => $data,
                    'lido'        => $m['lido'],
                ];
            }

            return ['uidvalidity' => $validade, 'novas' => $mensagens, 'lidos' => $lidos];
        } finally {
            $imap->sair();
        }
    }

    /* ---------------- Gmail API ---------------- */

    private function lerGmail(EmailConta $conta, array $conhecidos, int $dias, int $limite): array
    {
        $token = Google::token($conta);
        $lista = Http::withToken($token)->timeout(20)->get(Google::API . '/messages', [
            'q' => 'in:inbox newer_than:' . $dias . 'd', 'maxResults' => min(500, max($limite, count($conhecidos) + $limite)),
        ]);
        if (! $lista->successful()) throw new EmailErro('O Gmail recusou a leitura (' . $lista->status() . '). Conecte a conta de novo.');

        $conhecidosSet = array_flip($conhecidos);
        $novas = [];
        $lidos = [];
        foreach ((array) $lista->json('messages', []) as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') continue;
            $ehNova = ! isset($conhecidosSet[$id]);
            if ($ehNova && count($novas) >= $limite) continue;
            if (! $ehNova && count($lidos) >= 100) continue; // o lido/não lido só dos 100 mais recentes (uma chamada cada)
            $r = Http::withToken($token)->timeout(15)->get(Google::API . '/messages/' . $id, $ehNova
                ? ['format' => 'metadata', 'metadataHeaders' => ['From', 'Subject', 'Message-ID']]
                : ['format' => 'minimal']);
            if (! $r->successful()) continue;
            $lido = ! in_array('UNREAD', (array) $r->json('labelIds', []), true);
            if (! $ehNova) {
                $lidos[$id] = $lido;
                continue;
            }
            $cab = [];
            foreach ((array) $r->json('payload.headers', []) as $h) $cab[strtolower($h['name'] ?? '')] = (string) ($h['value'] ?? '');
            [$nome, $email] = Mime::remetente($cab['from'] ?? '');
            $novas[] = [
                'uid'         => $id,
                'message_id'  => mb_substr(trim($cab['message-id'] ?? '', '<> '), 0, 250) ?: null,
                'de_nome'     => $nome ?: null,
                'de_email'    => $email ?: null,
                'assunto'     => mb_substr(Mime::decodificar($cab['subject'] ?? ''), 0, 300) ?: null,
                'trecho'      => mb_substr(html_entity_decode((string) $r->json('snippet'), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 220) ?: null,
                'recebido_em' => $r->json('internalDate') ? Carbon::createFromTimestampMs((int) $r->json('internalDate')) : null,
                'lido'        => $lido,
            ];
        }

        return ['uidvalidity' => null, 'novas' => $novas, 'lidos' => $lidos];
    }
}
