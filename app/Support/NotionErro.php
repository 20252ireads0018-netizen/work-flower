<?php

namespace App\Support;

use RuntimeException;

/** Erro da API do Notion com a mensagem pronta para a tela e o status HTTP que o Notion devolveu. */
class NotionErro extends RuntimeException
{
    public function __construct(string $mensagem, public readonly int $status = 0)
    {
        parent::__construct($mensagem);
    }
}
