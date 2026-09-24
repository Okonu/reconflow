<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use Modules\ExceptionManagement\Enums\ExceptionPermission;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Users\Models\User;

final class QueueSummary
{
    public function counts(): array
    {
        $bySeverity = ReconException::query()->open()->groupBy('severity')->selectRaw('severity, count(*) as total')->pluck('total', 'severity')->all();

        return [
            'open' => ReconException::query()->open()->count(),
            'overdue' => ReconException::query()->open()->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'by_severity' => array_map('intval', $bySeverity),
            'needs_review' => ReconException::query()->open()->where('needs_review', true)->count(),
        ];
    }

    public function owners(): array
    {
        return User::query()->active()->with(['roles.permissions', 'permissions'])->orderBy('name')->get()
            ->filter(fn (User $u): bool => in_array(ExceptionPermission::Work->value, $u->permissionCodes(), true))
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
            ->values()->all();
    }
}
