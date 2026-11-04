<?php

namespace App\Support;

/**
 * Monta um .zip sem depender da extensão zip do PHP (nem toda hospedagem tem).
 * Usa deflate (gzdeflate, da zlib) quando existe; senão guarda sem compressão.
 * Serve para arquivos pequenos (o pacote do agente), tudo em memória.
 */
class ZipSimples
{
    private array $arquivos = [];

    public function adicionar(string $nome, string $conteudo): void
    {
        $this->arquivos[] = [str_replace('\\', '/', $nome), $conteudo];
    }

    public function gerar(): string
    {
        $dados = '';
        $central = '';
        [$hora, $data] = $this->dataDos();

        foreach ($this->arquivos as [$nome, $conteudo]) {
            $crc = crc32($conteudo);
            $tam = strlen($conteudo);
            $comp = function_exists('gzdeflate') ? gzdeflate($conteudo, 6) : false;
            [$metodo, $corpo] = $comp !== false && strlen($comp) < $tam ? [8, $comp] : [0, $conteudo];
            $flags = 0x0800; // nomes em UTF-8
            $offset = strlen($dados);

            $dados .= pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, $metodo, $hora, $data, $crc, strlen($corpo), $tam, strlen($nome), 0)
                . $nome . $corpo;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, $metodo, $hora, $data, $crc, strlen($corpo), $tam, strlen($nome), 0, 0, 0, 0, 0, $offset)
                . $nome;
        }

        $n = count($this->arquivos);

        return $dados . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), strlen($dados), 0);
    }

    /** Data/hora atuais no formato do MS-DOS usado pelo zip. */
    private function dataDos(): array
    {
        $t = getdate();

        return [
            ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
            (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'],
        ];
    }
}
