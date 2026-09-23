<?php

declare(strict_types=1);

namespace Modules\Audit\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Audit\Enums\AuditPermission;

final class AuditEventPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, AuditPermission::View);
    }

    public function verify(User $user): Response
    {
        return $this->permit($user, AuditPermission::Verify);
    }

    public function export(User $user): Response
    {
        return $this->permit($user, AuditPermission::Export);
    }
}
