<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Rbac\Actions\CreateRole;
use Modules\Rbac\Actions\DeleteRole;
use Modules\Rbac\Actions\UpdateRole;
use Modules\Rbac\Http\Requests\StoreRoleRequest;
use Modules\Rbac\Http\Requests\UpdateRoleRequest;
use Modules\Rbac\Http\Resources\PermissionResource;
use Modules\Rbac\Http\Resources\RoleResource;
use Modules\Rbac\Models\Permission;
use Modules\Rbac\Models\Role;

final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Rbac/Roles/Index', [
            'roles' => RoleResource::collection(Role::query()->with('permissions')->withCount('users')->orderBy('label')->get()),
            'permissions' => PermissionResource::collection(Permission::query()->orderBy('group')->orderBy('name')->get()),
            'can' => ['create' => $request->user()?->can('create', Role::class) ?? false],
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRole $create): RedirectResponse
    {
        $role = $create->handle($request->user(), $request->toData());

        return back()->with('success', "Role {$role->label} created");
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $update): RedirectResponse
    {
        $update->handle($request->user(), $role, $request->toChanges());

        return back()->with('success', "Role {$role->label} updated");
    }

    public function destroy(Request $request, Role $role, DeleteRole $delete): RedirectResponse
    {
        $this->authorize('delete', $role);
        $delete->handle($request->user(), $role);

        return back()->with('success', 'Role deleted');
    }
}
