<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\ExceptionManagement\Enums\ExceptionPermission;
use Modules\ExceptionManagement\Models\ReconException;

final class ExceptionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, ExceptionPermission::View);
    }

    public function view(User $user, ReconException $exception): Response
    {
        return $this->permit($user, ExceptionPermission::View);
    }

    public function work(User $user, ReconException $exception): Response
    {
        return $this->permit($user, ExceptionPermission::Work);
    }

    public function assign(User $user): Response
    {
        return $this->permit($user, ExceptionPermission::Assign);
    }
}
