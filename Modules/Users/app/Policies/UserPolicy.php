<?php

declare(strict_types=1);

namespace Modules\Users\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Users\Enums\UserPermission;
use Modules\Users\Models\User;

final class UserPolicy
{
    use AuthorizesPermissions;

    public function viewAny(AuthUser $user): Response
    {
        return $this->permit($user, UserPermission::View);
    }

    public function create(AuthUser $user): Response
    {
        return $this->permit($user, UserPermission::Manage);
    }

    public function update(AuthUser $user, User $target): Response
    {
        return $this->permit($user, UserPermission::Manage);
    }
}
