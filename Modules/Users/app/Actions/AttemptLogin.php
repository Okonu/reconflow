<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditLogger;
use Modules\Users\DTOs\Credentials;
use Modules\Users\Enums\UserAuditAction;
use Modules\Users\Models\User;

final class AttemptLogin
{
    private const GENERIC_FAILURE = 'These credentials do not match our records.';

    private static ?string $timingHash = null;

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Credentials $credentials): User
    {
        $key = $credentials->throttleKey();
        $email = mb_strtolower($credentials->email);

        if (RateLimiter::tooManyAttempts($key, (int) config('users.login.max_attempts'))) {
            $this->audit->record(UserAuditAction::LoginThrottled, $email, 'user');
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ])->status(429);
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $valid = Hash::check($credentials->password, $user->password ?? (self::$timingHash ??= Hash::make('timing-equaliser')));

        if ($user === null || ! $valid || ! $user->is_active) {
            RateLimiter::hit($key, (int) config('users.login.decay_seconds'));
            $this->audit->record(UserAuditAction::LoginFailed, $email, 'user', $user?->id, [
                'reason' => $user !== null && $valid ? 'inactive' : 'invalid_credentials',
            ]);
            throw ValidationException::withMessages(['email' => self::GENERIC_FAILURE]);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record(UserAuditAction::LoginSucceeded, $user, 'user', $user->id);

        return $user;
    }
}
