<?php

namespace App\Services;

class PaymeException extends \RuntimeException
{
    public function __construct(
        private readonly int $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }
}
