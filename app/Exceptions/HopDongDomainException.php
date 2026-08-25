<?php

namespace App\Exceptions;

use RuntimeException;

final class HopDongDomainException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
