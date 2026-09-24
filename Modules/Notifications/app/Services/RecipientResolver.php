<?php

declare(strict_types=1);

namespace Modules\Notifications\Services;

use Illuminate\Support\Collection;
use Modules\Notifications\Enums\NotificationPermission;
use Modules\Users\Models\User;

final class RecipientResolver
{
    public function for(NotificationPermission $permission): Collection
    {
        return User::query()->active()->with(['roles.permissions', 'permissions'])->orderBy('id')->get()
            ->filter(fn (User $user): bool => in_array($permission->value, $user->permissionCodes(), true))
            ->values();
    }
}
