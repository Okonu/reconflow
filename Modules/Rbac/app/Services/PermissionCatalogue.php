<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use App\Exceptions\DomainException;
use App\Support\Authorization\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Enums\RbacAuditAction;
use Modules\Rbac\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class PermissionCatalogue
{
    public const GUARD = 'web';

    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function sync(): void
    {
        DB::transaction(function (): void {
            $existing = Permission::query()->where('guard_name', self::GUARD)->get()->keyBy('name');
            $wanted = [];
            foreach ($this->registry->all() as $permission) {
                $wanted[(string) $permission->value] = $permission;
            }

            $added = array_values(array_diff(array_keys($wanted), $existing->keys()->all()));
            $removed = array_values(array_diff($existing->keys()->all(), array_keys($wanted)));

            foreach ($wanted as $code => $permission) {
                Permission::query()->updateOrCreate(
                    ['name' => $code, 'guard_name' => self::GUARD],
                    ['group' => $permission->group(), 'description' => $permission->description()],
                );
            }
            Permission::query()->where('guard_name', self::GUARD)->whereIn('name', $removed)->delete();

            if ($added !== [] || $removed !== []) {
                $this->audit->record(RbacAuditAction::PermissionsSynced, entityType: 'permission', payload: [
                    'added' => $added,
                    'removed' => $removed,
                ]);
            }
        });

        $this->registrar->forgetCachedPermissions();
    }

    public function assertKnown(array $codes): void
    {
        $unknown = array_values(array_diff($codes, $this->registry->codes()));
        if ($unknown !== []) {
            throw DomainException::invalid('Unknown permissions: '.implode(', ', $unknown));
        }
    }
}
