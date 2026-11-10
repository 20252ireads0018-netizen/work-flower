<?php

namespace App\Support;

/**
 * Pacote .zip do agente do Windows (pasta agente/ do código), com o endereço do site em site.txt.
 * Usado pelo botão "Baixar o agente" (Automações) e pelo instalador do app (API do agente, /api/agente/pacote).
 */
class PacoteAgente
{
    /** Arquivos que o agente instalado compara para saber se precisa se atualizar (mesma ordem no agente.ps1). */
    public const ARQUIVOS_VERSAO = ['agente.ps1', 'expansor.cs', 'desinstalar.ps1'];

    /**
     * "Versão" do agente: sha256 dos bytes de ARQUIVOS_VERSAO, cada um seguido de um byte zero (o agente.ps1 calcula
     * igual sobre os arquivos instalados). Vazio se os arquivos não estão no servidor.
     */
    public static function versao(): string
    {
        $pasta = base_path('agente');
        $ctx = hash_init('sha256');
        foreach (self::ARQUIVOS_VERSAO as $f) {
            if (! is_file($pasta . '/' . $f)) {
                return '';
            }
            hash_update($ctx, (string) file_get_contents($pasta . '/' . $f));
            hash_update($ctx, "\0");
        }

        return hash_final($ctx);
    }

    /** Conteúdo do .zip, ou null se os arquivos do agente não estão no servidor. */
    public static function gerar(): ?string
    {
        $pasta = base_path('agente');
        if (! is_file($pasta . '/agente.ps1')) {
            return null;
        }

        // ZipSimples: não depende da extensão zip do PHP
        $zip = new ZipSimples();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arq) {
            $rel = str_replace('\\', '/', substr($arq->getPathname(), strlen($pasta) + 1));
            // nunca leva configuração ou registro de quem gerou o pacote
            if (preg_match('#(^|/)(config\.json|agente\.log(\.1)?|site\.txt|\.gitignore)$#', $rel)) {
                continue;
            }
            $zip->adicionar('work-flower-agente/' . $rel, (string) file_get_contents($arq->getPathname()));
        }
        $zip->adicionar('work-flower-agente/site.txt', rtrim(url('/'), '/'));

        return $zip->gerar();
    }
}
