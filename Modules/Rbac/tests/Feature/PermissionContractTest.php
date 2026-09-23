<?php

declare(strict_types=1);

use App\Support\Authorization\PermissionRegistry;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Rbac\Models\Role;

const PERMISSION_CONTRACT = [
    'audit log' => ['GET', 'audit.index', [], 'audit.view'],
    'audit verify' => ['POST', 'audit.verify', [], 'audit.verify'],
    'roles list' => ['GET', 'rbac.roles.index', [], 'roles.view'],
    'roles create' => ['POST', 'rbac.roles.store', [], 'roles.manage'],
    'roles update' => ['PATCH', 'rbac.roles.update', ['role' => 'spare'], 'roles.manage'],
    'roles delete' => ['DELETE', 'rbac.roles.destroy', ['role' => 'spare'], 'roles.manage'],
    'assign roles' => ['PUT', 'rbac.users.roles', ['assignee' => 'self'], 'roles.manage'],
    'users list' => ['GET', 'users.index', [], 'users.view'],
    'users create' => ['POST', 'users.store', [], 'users.manage'],
    'users update' => ['PATCH', 'users.update', ['user' => 'self'], 'users.manage'],
    'uploads page' => ['GET', 'ingestion.uploads.index', [], 'uploads.view'],
    'upload file' => ['POST', 'ingestion.uploads.store', [], 'uploads.create'],
    'download template' => ['GET', 'ingestion.uploads.template', ['source' => '=sales'], 'uploads.view'],
    'sample pack' => ['GET', 'ingestion.uploads.sample-pack', [], 'uploads.view'],
    'upload preview' => ['GET', 'ingestion.uploads.show', ['staging' => 'staging'], 'uploads.view'],
    'confirm upload' => ['POST', 'ingestion.uploads.confirm', ['staging' => 'staging'], 'uploads.create'],
    'cancel upload' => ['POST', 'ingestion.uploads.cancel', ['staging' => 'staging'], 'uploads.create'],
    'batches' => ['GET', 'ingestion.batches.index', [], 'batches.view'],
    'batch detail' => ['GET', 'ingestion.batches.show', ['batch' => 'batch'], 'batches.view'],
    'reset demo' => ['POST', 'ingestion.demo.reset', [], 'demo.reset'],
];

dataset('protected routes', PERMISSION_CONTRACT);

const OPEN_TO_ANY_AUTHENTICATED_USER = ['home', 'logout'];

function contractStaging($user): UploadStaging
{
    return UploadStaging::query()->create([
        'source' => 'sales', 'business_date' => '2026-09-22', 'filename' => 'f.csv', 'extension' => 'csv', 'size_bytes' => 1,
        'checksum' => str_repeat('a', 64), 'uploaded_by' => $user->id, 'header_check' => ['ok' => false, 'message' => 'x'],
        'rows' => [], 'state' => 'staged', 'expires_at' => now()->addDay(),
    ]);
}

function contractBatch(): SourceBatch
{
    return SourceBatch::query()->create([
        'source' => 'sales', 'business_date' => '2026-09-22', 'version' => random_int(1, 1000000), 'origin' => 'upload', 'status' => 'active',
        'mode' => 'replace', 'checksum' => str_repeat('b', 64), 'rows_received' => 0, 'rows_loaded' => 0, 'rows_quarantined' => 0,
        'dq_summary' => ['reasons' => []],
    ]);
}

function contractUrl(string $name, array $params, $user): string
{
    $resolved = array_map(fn (string $v) => match (true) {
        $v === 'spare' => Role::query()->create(['name' => 'spare_'.uniqid(), 'guard_name' => 'web', 'label' => 'Spare'])->id,
        $v === 'self' => $user->id,
        $v === 'staging' => contractStaging($user)->id,
        $v === 'batch' => contractBatch()->id,
        str_starts_with($v, '=') => substr($v, 1),
    }, $params);

    return route($name, $resolved);
}

it('denies the route to a user holding every permission except the one it needs', function (string $method, string $name, array $params, string $permission): void {
    $everythingElse = collect(app(PermissionRegistry::class)->codes())->reject(fn ($c) => $c === $permission)->all();
    $user = userWithPermissions($everythingElse);

    $this->actingAs($user)->json($method, contractUrl($name, $params, $user), [])->assertForbidden();
})->with('protected routes');

it('allows the route to a user holding only the permission it needs', function (string $method, string $name, array $params, string $permission): void {
    $user = userWithPermissions([$permission]);

    expect($this->actingAs($user)->json($method, contractUrl($name, $params, $user), [])->getStatusCode())->not->toBe(403);
})->with('protected routes');

it('covers every authenticated route with a permission contract', function (): void {
    $named = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r) => in_array('auth', $r->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $r) => $r->getName())
        ->reject(fn (?string $n) => $n === null || in_array($n, OPEN_TO_ANY_AUTHENTICATED_USER, true))
        ->sort()->values()->all();
    $declared = collect(PERMISSION_CONTRACT)->pluck(1)->sort()->values()->all();

    expect($named)->toBe($declared);
});
