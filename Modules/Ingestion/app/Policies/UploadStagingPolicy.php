<?php

declare(strict_types=1);

namespace Modules\Ingestion\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Ingestion\Enums\IngestionPermission;
use Modules\Ingestion\Models\UploadStaging;

final class UploadStagingPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, IngestionPermission::ViewUploads);
    }

    public function view(User $user, UploadStaging $staging): Response
    {
        return $this->permit($user, IngestionPermission::ViewUploads);
    }

    public function create(User $user): Response
    {
        return $this->permit($user, IngestionPermission::CreateUploads);
    }

    public function confirm(User $user, UploadStaging $staging): Response
    {
        return $this->permit($user, IngestionPermission::CreateUploads);
    }

    public function cancel(User $user, UploadStaging $staging): Response
    {
        return $this->permit($user, IngestionPermission::CreateUploads);
    }
}
