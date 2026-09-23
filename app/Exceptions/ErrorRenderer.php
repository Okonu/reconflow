<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ErrorRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);

        $exceptions->render(function (DomainException $e, Request $request): JsonResponse|RedirectResponse {
            if (self::wantsJson($request)) {
                return self::json($e->status, $e->errorCode, $e->getMessage());
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! self::wantsJson($request)) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => self::json(422, 'validation_error', 'The given data was invalid.', ['fields' => $e->errors()]),
                $e instanceof AuthenticationException => self::json(401, 'unauthenticated', 'Authentication required.'),
                $e instanceof AuthorizationException => self::json(403, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.'),
                $e instanceof ModelNotFoundException => self::json(404, 'not_found', 'Resource not found.'),
                $e instanceof HttpExceptionInterface => self::json($e->getStatusCode(), self::codeFor($e->getStatusCode()), $e->getMessage() ?: 'Request failed.'),
                default => self::json(500, 'internal_error', 'An unexpected error occurred.'),
            };
        });
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            419 => 'csrf_token_mismatch',
            429 => 'rate_limited',
            default => 'http_error',
        };
    }

    private static function wantsJson(Request $request): bool
    {
        return ! $request->header('X-Inertia') && ($request->expectsJson() || $request->is('api/*'));
    }

    private static function json(int $status, string $code, string $message, array $extra = []): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message, 'request_id' => Context::get('request_id'), ...$extra],
        ], $status);
    }
}
