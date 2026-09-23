<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Actions\AttemptLogin;
use Modules\Users\Actions\Logout;
use Modules\Users\Http\Requests\LoginRequest;

final class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Users/Auth/Login');
    }

    public function store(LoginRequest $request, AttemptLogin $login): RedirectResponse
    {
        $login->handle($request->credentials());

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request, Logout $logout): RedirectResponse
    {
        $logout->handle($request->user());

        return redirect()->route('login');
    }
}
