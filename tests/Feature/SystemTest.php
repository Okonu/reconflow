<?php

declare(strict_types=1);

it('reports liveness, readiness and prometheus metrics', function (): void {
    $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    $this->get('/ready')->assertOk()->assertJson(['database' => 'ok']);
    $this->get('/metrics')->assertOk()->assertSee('reconflow_audit_events');
});

it('sets security headers and a nonce-based content security policy', function (): void {
    $response = $this->get(route('login'));
    $csp = (string) $response->headers->get('Content-Security-Policy');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    expect($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->and($csp)->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('echoes a well-formed request id and replaces a malformed one', function (): void {
    expect($this->get('/health', ['X-Request-ID' => 'trace-12345678'])->headers->get('X-Request-ID'))->toBe('trace-12345678')
        ->and($this->get('/health', ['X-Request-ID' => 'bad id!'])->headers->get('X-Request-ID'))->not->toBe('bad id!');
});

it('returns the JSON error envelope for JSON clients', function (): void {
    $this->actingAs(demoUser('admin@demo'))
        ->postJson(route('rbac.roles.store'), [])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['code', 'message', 'request_id', 'fields']]);

    $this->actingAs(demoUser('analyst@demo'))
        ->getJson(route('users.index'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden')
        ->assertJsonPath('error.message', 'Missing permission: users.view');
});
