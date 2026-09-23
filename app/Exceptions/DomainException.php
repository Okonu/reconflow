<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    final public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $errorCode = 'domain_error',
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $message): static
    {
        return new static($message, 409, 'conflict');
    }

    public static function invalid(string $message): static
    {
        return new static($message, 422, 'invalid_request');
    }

    public static function notFound(string $message): static
    {
        return new static($message, 404, 'not_found');
    }
}
