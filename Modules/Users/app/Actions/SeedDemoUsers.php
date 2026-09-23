<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Support\Authorization\DefaultRole;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Models\Role;
use Modules\Users\DTOs\NewUserData;
use Modules\Users\Enums\UserAuditAction;
use Modules\Users\Models\User;

final class SeedDemoUsers
{
    public const USERS = [
        'analyst@demo' => ['Demo Analyst', DefaultRole::ReconAnalyst],
        'manager@demo' => ['Demo Finance Manager', DefaultRole::FinanceManager],
        'auditor@demo' => ['Demo Auditor', DefaultRole::Auditor],
        'admin@demo' => ['Demo Administrator', DefaultRole::Administrator],
    ];

    public function __construct(private readonly CreateUser $create) {}

    public function handle(string $password): void
    {
        foreach (self::USERS as $email => [$name, $role]) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }
            $roleId = Role::query()->where('name', $role->value)->value('id');
            if ($roleId === null) {
                continue;
            }
            $this->create->handle(
                AuditLogger::SYSTEM_ACTOR,
                new NewUserData($name, $email, $password, null, [(int) $roleId]),
                UserAuditAction::UserSeeded,
            );
        }
    }
}
