<?php

namespace App\Exceptions;

use RuntimeException;

final class NhanVienDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $domainCode,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message);
    }
}
