<?php

declare(strict_types=1);

namespace Modules\Users\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Users\Models\User;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        assert($user instanceof User);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'region' => $user->region,
            'is_active' => $user->is_active,
            'roles' => $user->roles->map(fn (Model $role): array => ['id' => $role->getKey(), 'code' => $role->getAttribute('name'), 'label' => $role->getAttribute('label')])->values(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'can' => ['update' => $request->user()?->can('update', $user) ?? false],
        ];
    }
}
