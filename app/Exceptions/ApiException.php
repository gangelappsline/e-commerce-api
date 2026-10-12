<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 400,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
