<?php

namespace App\Support;

/**
 * Pacote .zip do agente do Windows (pasta agente/ do código), com o endereço do site em site.txt.
 * Usado pelo botão "Baixar o agente" (Automações) e pelo instalador do app (API do agente, /api/agente/pacote).
 */
class PacoteAgente
{
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
