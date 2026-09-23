<?php

declare(strict_types=1);

namespace App\Contracts;

use BackedEnum;

interface PermissionEnum extends BackedEnum
{
    public function description(): string;

    public function group(): string;

    public function defaultRoles(): array;
}
