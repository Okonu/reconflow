<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Rbac\Models\Role;

final class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->resource;
        assert($role instanceof Role);

        return [
            'id' => $role->id,
            'code' => $role->name,
            'label' => $role->label,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->permissionCodes(),
            'user_count' => $this->whenCounted('users'),
            'can' => [
                'update' => $request->user()?->can('update', $role) ?? false,
                'delete' => $request->user()?->can('delete', $role) ?? false,
            ],
        ];
    }
}
