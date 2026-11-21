<?php

namespace App\Exceptions;

use RuntimeException;

class VoucherException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 422,
        public readonly array $meta = []
    ) {
        parent::__construct($message);
    }
}