<?php

namespace App\Exceptions;

use RuntimeException;

/** Erro de regra de negócio (já assinou, falta CPF etc.). $status é o HTTP sugerido. */
class SubscriptionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

