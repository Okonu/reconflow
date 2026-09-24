<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use Modules\ExceptionManagement\Enums\ExceptionPermission;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Users\Models\User;

final class OwnerAssigner
{
    private ?array $preparers = null;

    public function assign(?string $region): ?int
    {
        $preparers = $this->preparers();
        if ($preparers === []) {
            return null;
        }
        $regional = array_values(array_filter($preparers, fn (array $p): bool => $region !== null && $p['region'] === $region));
        $pool = $regional !== [] ? $regional : $preparers;
        $loads = ReconException::query()->open()->whereIn('owner_id', array_column($pool, 'id'))
            ->groupBy('owner_id')->selectRaw('owner_id, count(*) as open_count')->pluck('open_count', 'owner_id')->all();
        usort($pool, fn (array $a, array $b): int => [(int) ($loads[$a['id']] ?? 0), $a['id']] <=> [(int) ($loads[$b['id']] ?? 0), $b['id']]);

        return $pool[0]['id'];
    }

    private function preparers(): array
    {
        return $this->preparers ??= User::query()->active()->with(['roles.permissions', 'permissions'])->orderBy('id')->get()
            ->filter(fn (User $u): bool => in_array(ExceptionPermission::Work->value, $u->permissionCodes(), true) && ! in_array('runs.signoff', $u->permissionCodes(), true))
            ->map(fn (User $u): array => ['id' => $u->id, 'region' => $u->region])
            ->values()->all();
    }
}
