<?php

namespace App\Support\Armazenamento;

/**
 * O que um destino de armazenamento sabe fazer. Erros viram RuntimeException com mensagem para a tela.
 * $caminho é relativo à pasta do Work Flower no destino (ex.: "backups/2026-11-06-0300.json").
 */
interface Destino
{
    /** Grava o arquivo e devolve o identificador remoto (chave do objeto ou id do arquivo no Drive). */
    public function enviar(string $caminho, string $conteudo, string $mime): string;

    public function baixar(string $remoto): string;

    /** Apaga; arquivo que já não existe não é erro. */
    public function apagar(string $remoto): void;

    /** Confere se as credenciais funcionam (grava e apaga um arquivo pequeno). */
    public function testar(): void;

    /** Endereço temporário para o navegador baixar direto do destino, ou null se o site precisa repassar. */
    public function urlTemporaria(string $remoto, int $segundos = 300): ?string;
}
