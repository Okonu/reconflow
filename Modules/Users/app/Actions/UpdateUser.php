<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Contracts\AuditActor;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Services\AdministrationGuard;
use Modules\Users\DTOs\UserChanges;
use Modules\Users\Enums\UserAuditAction;
use Modules\Users\Models\User;

final class UpdateUser
{
    public function __construct(
        private readonly AdministrationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(AuditActor $actor, User $user, UserChanges $changes): User
    {
        return DB::transaction(function () use ($actor, $user, $changes): User {
            $user->fill(array_filter([
                'name' => $changes->name,
                'region' => $changes->region,
                'is_active' => $changes->isActive,
            ], fn ($v) => $v !== null));
            $dirty = array_keys($user->getDirty());
            $this->guard->preserving(fn (): bool => $user->save());

            if ($dirty !== []) {
                $this->audit->record(UserAuditAction::UserUpdated, $actor, 'user', $user->id, [
                    'changed' => $dirty,
                    'is_active' => $user->is_active,
                ]);
            }

            return $user;
        });
    }
}
