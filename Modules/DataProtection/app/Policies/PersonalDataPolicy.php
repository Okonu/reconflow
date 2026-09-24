<?php

declare(strict_types=1);

namespace Modules\DataProtection\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\DataProtection\Enums\DataProtectionPermission;

final class PersonalDataPolicy
{
    use AuthorizesPermissions;

    public function unmask(User $user): Response
    {
        return $this->permit($user, DataProtectionPermission::UnmaskPersonalData);
    }
}
