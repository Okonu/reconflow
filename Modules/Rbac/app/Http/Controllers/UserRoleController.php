<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Controllers;

use App\Contracts\RoleAssignable;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Rbac\Actions\AssignUserRoles;
use Modules\Rbac\Http\Requests\AssignRolesRequest;

final class UserRoleController extends Controller
{
    public function __invoke(AssignRolesRequest $request, RoleAssignable $assignee, AssignUserRoles $assign): RedirectResponse
    {
        $assign->handle($request->user(), $assignee, $request->roleIds());

        return back()->with('success', 'Roles updated');
    }
}
