<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\SettingsSection;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;

final class SettingsPolicy
{
    public function view(User $user, SettingsSection $section): Response
    {
        return $user->can($section->viewPermission()) || $user->can($section->managePermission())
            ? Response::allow()
            : Response::deny("Missing permission: {$section->viewPermission()}");
    }

    public function manage(User $user, SettingsSection $section): Response
    {
        return $user->can($section->managePermission())
            ? Response::allow()
            : Response::deny("Missing permission: {$section->managePermission()}");
    }
}
