<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}

