<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Actions\CreateUser;
use Modules\Users\Actions\UpdateUser;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Http\Resources\UserResource;
use Modules\Users\Models\User;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Users/Index', [
            'users' => UserResource::collection(User::query()->with('roles')->orderBy('email')->get()),
            'can' => ['create' => $request->user()?->can('create', User::class) ?? false],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $create): RedirectResponse
    {
        $user = $create->handle($request->user(), $request->toData());

        return back()->with('success', "User {$user->email} created");
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): RedirectResponse
    {
        $update->handle($request->user(), $user, $request->toChanges());

        return back()->with('success', "User {$user->email} updated");
    }
}
