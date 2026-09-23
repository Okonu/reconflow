<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Contracts\PermissionEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;

trait AuthorizesPermissions
{
    protected function permit(User $user, PermissionEnum $permission): Response
    {
        return $user->can((string) $permission->value)
            ? Response::allow()
            : Response::deny("Missing permission: {$permission->value}");
    }
}
