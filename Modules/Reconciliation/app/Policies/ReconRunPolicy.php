<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Reconciliation\Enums\ReconPermission;
use Modules\Reconciliation\Models\ReconRun;

final class ReconRunPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, ReconPermission::ViewRuns);
    }

    public function view(User $user, ReconRun $run): Response
    {
        return $this->permit($user, ReconPermission::ViewRuns);
    }

    public function create(User $user): Response
    {
        return $this->permit($user, ReconPermission::TriggerRuns);
    }

    public function viewResults(User $user): Response
    {
        return $this->permit($user, ReconPermission::ViewResults);
    }

    public function export(User $user): Response
    {
        return $this->permit($user, ReconPermission::ExportResults);
    }

    public function exportUnmasked(User $user): Response
    {
        return $this->permit($user, ReconPermission::ExportResultsUnmasked);
    }
}
