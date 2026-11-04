<?php

namespace App\Support\Emails;

/** Falha ao falar com a caixa de e-mail. A mensagem já vem pronta para mostrar à pessoa (sem senha nem detalhes internos). */
class EmailErro extends \RuntimeException
{
}
