<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Rbac\Models\Permission;

final class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permission = $this->resource;
        assert($permission instanceof Permission);

        return [
            'code' => $permission->name,
            'group' => $permission->group,
            'description' => $permission->description,
        ];
    }
}
