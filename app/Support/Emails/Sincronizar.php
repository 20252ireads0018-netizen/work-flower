<?php

namespace App\Support\Emails;

use App\Models\EmailConta;
use App\Models\EmailMensagem;

/** Puxa as mensagens recentes de uma caixa, classifica as novas e atualiza o lido/não lido das que já estavam aqui. */
class Sincronizar
{
    public const DIAS = 30;          // janela lida na caixa
    public const NOVAS_POR_VEZ = 150;
    public const GUARDAR_DIAS = 60;  // mensagens mais velhas que isso saem daqui (continuam na caixa)
    public const GUARDAR_MAX = 1000; // por conta

    /** @return int quantas mensagens novas entraram. Em caso de falha grava o erro na conta e relança. */
    public static function conta(EmailConta $conta): int
    {
        $leitor = app(LeitorEmails::class);
        $conhecidos = EmailMensagem::where('conta_id', $conta->id)->pluck('uid')->map(fn ($u) => (string) $u)->all();

        try {
            $r = $leitor->ler($conta, $conhecidos, self::DIAS, self::NOVAS_POR_VEZ);
        } catch (EmailErro $e) {
            $conta->forceFill(['erro' => mb_substr($e->getMessage(), 0, 300)])->save();
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $conta->forceFill(['erro' => 'Falha inesperada ao ler a caixa. Tente de novo mais tarde.'])->save();
            throw new EmailErro('Falha inesperada ao ler a caixa. Tente de novo mais tarde.', 0, $e);
        }

        // pasta recriada no servidor: os UIDs guardados apontam para outras mensagens
        if ($conta->uidvalidity && $r['uidvalidity'] && $conta->uidvalidity !== $r['uidvalidity']) {
            EmailMensagem::where('conta_id', $conta->id)->delete();
        }

        $regras = Classificador::regras($conta->user_id);
        $clientes = Classificador::emailsDeClientes($conta->user_id);
        $novas = 0;
        foreach ($r['novas'] as $m) {
            $msg = EmailMensagem::firstOrNew(['conta_id' => $conta->id, 'uid' => (string) $m['uid']]);
            $existia = $msg->exists;
            $msg->fill([
                'user_id'     => $conta->user_id,
                'message_id'  => $m['message_id'] ?? null,
                'de_nome'     => $m['de_nome'] ?? null,
                'de_email'    => $m['de_email'] ?? null,
                'assunto'     => $m['assunto'] ?? null,
                'trecho'      => $m['trecho'] ?? null,
                'recebido_em' => $m['recebido_em'] ?? now(),
                'lido'        => (bool) ($m['lido'] ?? false),
            ]);
            if (! $msg->categoria_manual) {
                $msg->categoria = Classificador::categoria($regras, $clientes, $msg->de_nome, $msg->de_email, $msg->assunto);
            }
            $msg->save();
            if (! $existia) $novas++;
        }

        foreach ($r['lidos'] as $uid => $lido) {
            EmailMensagem::where('conta_id', $conta->id)->where('uid', (string) $uid)->where('lido', ! $lido)->update(['lido' => $lido]);
        }

        // limpeza: só o recente fica guardado aqui
        EmailMensagem::where('conta_id', $conta->id)->where('recebido_em', '<', now()->subDays(self::GUARDAR_DIAS))->delete();
        $corte = EmailMensagem::where('conta_id', $conta->id)->orderByDesc('recebido_em')->skip(self::GUARDAR_MAX)->value('recebido_em');
        if ($corte) EmailMensagem::where('conta_id', $conta->id)->where('recebido_em', '<=', $corte)->delete();

        $conta->forceFill([
            'uidvalidity'     => $r['uidvalidity'] ?? $conta->uidvalidity,
            'sincronizado_em' => now(),
            'erro'            => null,
        ])->save();

        return $novas;
    }

    /** Reaplica as regras nas mensagens da pessoa (menos as que ela mudou à mão). */
    public static function reclassificar(int $userId): int
    {
        $regras = Classificador::regras($userId);
        $clientes = Classificador::emailsDeClientes($userId);
        $n = 0;
        EmailMensagem::where('user_id', $userId)->where('categoria_manual', false)->chunkById(300, function ($lote) use ($regras, $clientes, &$n) {
            foreach ($lote as $m) {
                $cat = Classificador::categoria($regras, $clientes, $m->de_nome, $m->de_email, $m->assunto);
                if ($cat !== $m->categoria) {
                    $m->forceFill(['categoria' => $cat])->save();
                    $n++;
                }
            }
        });

        return $n;
    }
}
