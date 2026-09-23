<?php

declare(strict_types=1);

namespace Modules\Users\DTOs;

final readonly class Credentials
{
    public function __construct(
        public string $email,
        public string $password,
        public string $ipAddress,
    ) {}

    public function throttleKey(): string
    {
        return 'login|'.mb_strtolower($this->email).'|'.$this->ipAddress;
    }
}
