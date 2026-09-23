<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use Illuminate\Support\Facades\Auth;
use Modules\Audit\Services\AuditLogger;
use Modules\Users\Enums\UserAuditAction;
use Modules\Users\Models\User;

final class Logout
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user): void
    {
        $this->audit->record(UserAuditAction::LoggedOut, $user, 'user', $user->id);
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
