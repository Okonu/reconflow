<?php

declare(strict_types=1);

namespace Modules\Ingestion\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Ingestion\Enums\IngestionPermission;
use Modules\Ingestion\Models\SourceBatch;

final class SourceBatchPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, IngestionPermission::ViewBatches);
    }

    public function view(User $user, SourceBatch $batch): Response
    {
        return $this->permit($user, IngestionPermission::ViewBatches);
    }

    public function resetDemo(User $user): Response
    {
        return $this->permit($user, IngestionPermission::ResetDemo);
    }
}
