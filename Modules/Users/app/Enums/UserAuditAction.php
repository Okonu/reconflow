<?php

declare(strict_types=1);

namespace Modules\Users\Enums;

enum UserAuditAction: string
{
    case LoginSucceeded = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case LoginThrottled = 'auth.login_rate_limited';
    case LoggedOut = 'auth.logout';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserSeeded = 'user.seeded';
}
