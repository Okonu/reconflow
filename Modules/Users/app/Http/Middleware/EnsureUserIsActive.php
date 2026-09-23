<?php

declare(strict_types=1);

namespace Modules\Users\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Users\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Account is inactive.']], 401)
                : redirect()->route('login');
        }

        return $next($request);
    }
}
