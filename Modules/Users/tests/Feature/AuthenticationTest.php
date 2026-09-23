<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Audit\Models\AuditEvent;

function auditActions(): array
{
    return AuditEvent::query()->orderBy('id')->pluck('action')->all();
}

beforeEach(fn () => RateLimiter::clear('login|analyst@demo|127.0.0.1'));

it('renders the login page for guests and redirects guests away from the app', function (): void {
    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Users/Auth/Login'));
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('logs in, shares permissions with the frontend and audits the login', function (): void {
    $this->post(route('login.store'), ['email' => 'analyst@demo', 'password' => DEMO_PASSWORD])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs(demoUser('analyst@demo'));
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('auth.user.email', 'analyst@demo')
        ->where('auth.user.roles', ['Recon Analyst'])
        ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('audit.view'))
        ->missing('auth.user.password'));
    expect(auditActions())->toContain('auth.login');
    expect(demoUser('analyst@demo')->last_login_at)->not->toBeNull();
});

it('matches email case-insensitively', function (): void {
    $this->post(route('login.store'), ['email' => 'Analyst@Demo', 'password' => DEMO_PASSWORD]);

    $this->assertAuthenticated();
});

it('rejects bad credentials with one generic message and audits each failure', function (): void {
    $wrong = $this->post(route('login.store'), ['email' => 'analyst@demo', 'password' => 'nope']);
    $unknown = $this->post(route('login.store'), ['email' => 'ghost@demo', 'password' => 'nope']);

    $wrong->assertSessionHasErrors('email');
    $unknown->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('These credentials do not match our records.');
    $this->assertGuest();
    expect(array_count_values(auditActions())['auth.login_failed'])->toBe(2);
});

it('rate limits repeated failed logins', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->post(route('login.store'), ['email' => 'analyst@demo', 'password' => 'bad']);
    }
    $this->post(route('login.store'), ['email' => 'analyst@demo', 'password' => DEMO_PASSWORD])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(auditActions())->toContain('auth.login_rate_limited');
});

it('blocks inactive users from logging in and ends their existing session', function (): void {
    $analyst = demoUser('analyst@demo');
    $this->actingAs($analyst)->get(route('home'))->assertOk();

    $analyst->forceFill(['is_active' => false])->save();
    $this->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => 'analyst@demo', 'password' => DEMO_PASSWORD])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs out and audits the logout', function (): void {
    $this->actingAs(demoUser('manager@demo'))->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(auditActions())->toContain('auth.logout');
});

it('stores passwords with Argon2id', function (): void {
    expect(demoUser('admin@demo')->password)->toStartWith('$argon2id$');
});
