<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Contracts\PermissionEnum;
use InvalidArgumentException;

final class PermissionRegistry
{
    private array $enums = [];

    private array $protected = [];

    public function register(string $enumClass): void
    {
        if (! is_subclass_of($enumClass, PermissionEnum::class)) {
            throw new InvalidArgumentException("{$enumClass} must implement ".PermissionEnum::class);
        }
        $this->enums[$enumClass] = $enumClass;
    }

    public function protect(PermissionEnum $permission): void
    {
        $this->protected[(string) $permission->value] = $permission;
    }

    public function protectedCodes(): array
    {
        $codes = array_keys($this->protected);
        sort($codes);

        return $codes;
    }

    public function all(): array
    {
        $permissions = [];
        foreach ($this->enums as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $permissions[$case->value] = $case;
            }
        }
        ksort($permissions);

        return array_values($permissions);
    }

    public function codes(): array
    {
        return array_map(fn (PermissionEnum $p): string => (string) $p->value, $this->all());
    }

    public function defaultsFor(DefaultRole $role): array
    {
        return array_values(array_filter(
            $this->codes(),
            fn (string $code): bool => in_array($role, $this->find($code)?->defaultRoles() ?? [], true),
        ));
    }

    public function find(string $code): ?PermissionEnum
    {
        foreach ($this->all() as $permission) {
            if ($permission->value === $code) {
                return $permission;
            }
        }

        return null;
    }
}
