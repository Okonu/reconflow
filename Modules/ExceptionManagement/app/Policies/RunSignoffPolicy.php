<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\ExceptionManagement\Enums\ExceptionPermission;

final class RunSignoffPolicy
{
    use AuthorizesPermissions;

    public function view(User $user): Response
    {
        return $this->permit($user, ExceptionPermission::View);
    }

    public function create(User $user): Response
    {
        return $this->permit($user, ExceptionPermission::SignOff);
    }

    public function reopen(User $user): Response
    {
        return $this->permit($user, ExceptionPermission::Reopen);
    }
}
