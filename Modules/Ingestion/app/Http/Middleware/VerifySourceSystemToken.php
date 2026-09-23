<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifySourceSystemToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('ingestion.sources.token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'A valid source-system token is required.']], 401);
        }

        return $next($request);
    }
}
