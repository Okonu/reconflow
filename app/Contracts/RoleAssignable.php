<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface RoleAssignable
{
    public function getKey();

    public function roles(): BelongsToMany;

    public function syncRoles(...$roles): static;
}
