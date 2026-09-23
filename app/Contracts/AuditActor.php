<?php

declare(strict_types=1);

namespace App\Contracts;

interface AuditActor
{
    public function auditActorId(): ?int;

    public function auditActorLabel(): string;
}
