<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Contracts\AuditActor;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Actions\AssignUserRoles;
use Modules\Users\DTOs\NewUserData;
use Modules\Users\Enums\UserAuditAction;
use Modules\Users\Models\User;

final class CreateUser
{
    public function __construct(
        private readonly AssignUserRoles $assignRoles,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(AuditActor|string $actor, NewUserData $data, UserAuditAction $action = UserAuditAction::UserCreated): User
    {
        return DB::transaction(function () use ($actor, $data, $action): User {
            if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($data->email)])->exists()) {
                throw DomainException::conflict('A user with this email already exists');
            }
            $user = User::query()->create([
                'name' => $data->name,
                'email' => mb_strtolower($data->email),
                'password' => $data->password,
                'region' => $data->region,
                'is_active' => true,
            ]);
            $this->audit->record($action, $actor, 'user', $user->id, ['region' => $user->region]);
            $this->assignRoles->handle($actor, $user, $data->roleIds);

            return $user;
        });
    }
}
