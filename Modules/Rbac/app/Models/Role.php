<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use Spatie\Permission\Models\Role as SpatieRole;

final class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'label', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function permissionCodes(): array
    {
        return $this->permissions->pluck('name')->sort()->values()->all();
    }
}
