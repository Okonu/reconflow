<?php

declare(strict_types=1);

use App\Support\Authorization\PermissionRegistry;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Adjustments\Models\Adjustment;
use Modules\AI\Models\AiSuggestion;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Rbac\Models\Role;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Models\ReconRun;

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
    'runs' => ['GET', 'runs.index', [], 'runs.view'],
    'run now' => ['POST', 'runs.store', [], 'runs.trigger'],
    'run detail' => ['GET', 'runs.show', ['run' => 'run'], 'runs.view'],
    'possible matches' => ['GET', 'results.possible-matches', ['result' => 'result'], 'results.view'],
    'confirm manual match' => ['POST', 'results.manual-match', ['result' => 'result'], 'matches.confirm'],
    'report' => ['GET', 'reports.reconciliation', [], 'results.view'],
    'report export' => ['GET', 'reports.reconciliation.export', [], 'results.export'],
    'report export unmasked' => ['POST', 'reports.reconciliation.export-unmasked', [], 'results.export_unmasked'],
    'fuzzy matches' => ['GET', 'matches.index', [], 'results.view'],
    'confirm fuzzy matches' => ['POST', 'matches.confirm', [], 'matches.confirm'],
    'reject fuzzy match' => ['POST', 'matches.reject', ['result' => 'result'], 'matches.confirm'],
    'exception queue' => ['GET', 'exceptions.index', [], 'exceptions.view'],
    'exception detail' => ['GET', 'exceptions.show', ['exception' => 'exception'], 'exceptions.view'],
    'assign exceptions' => ['POST', 'exceptions.assign', [], 'exceptions.assign'],
    'start review' => ['POST', 'exceptions.review', ['exception' => 'exception'], 'exceptions.work'],
    'resolve exception' => ['POST', 'exceptions.resolve', ['exception' => 'exception'], 'exceptions.work'],
    'comment on exception' => ['POST', 'exceptions.comment', ['exception' => 'exception'], 'exceptions.work'],
    'sign-off page' => ['GET', 'signoff.show', ['date' => '=2026-09-22'], 'exceptions.view'],
    'sign off' => ['POST', 'signoff.store', ['date' => '=2026-09-22'], 'runs.signoff'],
    'reopen date' => ['POST', 'signoff.reopen', ['date' => '=2026-09-22'], 'runs.reopen'],
    'approvals inbox' => ['GET', 'adjustments.index', [], 'adjustments.view'],
    'propose adjustment' => ['POST', 'adjustments.store', ['exception' => 'exception'], 'adjustments.propose'],
    'approve adjustment' => ['POST', 'adjustments.approve', ['adjustment' => 'adjustment'], 'adjustments.approve'],
    'reject adjustment' => ['POST', 'adjustments.reject', ['adjustment' => 'adjustment'], 'adjustments.approve'],
    'retry posting' => ['POST', 'adjustments.retry', ['adjustment' => 'failed_adjustment'], 'adjustments.approve'],
    'simulate ERP failure' => ['POST', 'adjustments.erp-failure', [], 'erp.manage'],
    'AI oversight' => ['GET', 'ai.oversight', [], 'ai.oversee'],
    'AI kill switch' => ['POST', 'ai.kill-switch', [], 'ai.manage'],
    'AI triage' => ['POST', 'ai.triage', ['exception' => 'exception'], 'ai.use'],
    'AI batch triage' => ['POST', 'ai.triage-batch', [], 'ai.use'],
    'AI decision' => ['POST', 'ai.decide', ['suggestion' => 'suggestion'], 'ai.use'],
    'AI summary' => ['GET', 'ai.summary.show', ['run' => 'run'], 'runs.view'],
    'AI summary generate' => ['POST', 'ai.summary.store', ['run' => 'run'], 'ai.use'],
    'audit export' => ['GET', 'audit.export', [], 'audit.export'],
    'settings' => ['GET', 'settings.index', [], 'config.view'],
    'update rule settings' => ['PUT', 'settings.update', ['section' => '=rules'], 'config.manage'],
    'unmask' => ['POST', 'pii.unmask', [], 'pii.unmask'],
    'erasure request' => ['POST', 'privacy.erasures.store', [], 'privacy.erase'],
];

dataset('protected routes', PERMISSION_CONTRACT);

const OPEN_TO_ANY_AUTHENTICATED_USER = ['home', 'logout', 'notifications.index', 'notifications.read', 'notifications.read-all'];

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
        'source' => 'sales', 'business_date' => '2026-'.sprintf('%02d-%02d', random_int(1, 12), random_int(1, 28)), 'version' => random_int(1, 1000000), 'origin' => 'upload', 'status' => 'active',
        'mode' => 'upload_replace', 'checksum' => str_repeat('b', 64), 'rows_received' => 0, 'rows_loaded' => 0, 'rows_quarantined' => 0,
        'dq_summary' => ['reasons' => []],
    ]);
}

function contractResult(): ReconResult
{
    $run = ReconRun::query()->create(['business_date' => '2026-09-22', 'version' => random_int(1, 1000000), 'status' => 'completed', 'trigger' => 'manual', 'rule_config' => []]);

    return ReconResult::query()->create([
        'run_id' => $run->id, 'business_date' => '2026-09-22', 'section' => 'current', 'transaction_id' => 'T-X', 'payment_ids' => [],
        'status' => 'MISSING_PAYMENT', 'roll_up' => 'Exception', 'rule_id' => 'R7', 'payment_record_ids' => [], 'payment_identities' => [], 'flags' => [],
    ]);
}

function contractException(): ReconException
{
    $result = contractResult();

    return ReconException::query()->create([
        'key' => 'k-'.uniqid(), 'identity' => 'i-'.uniqid(), 'business_date' => '2026-09-22', 'result_id' => $result->id, 'run_id' => $result->run_id,
        'status' => 'MISSING_PAYMENT', 'family' => 'missing_payment', 'category' => 'Missing payment', 'severity' => 'low', 'amount_at_risk' => '10.00',
        'transaction_id' => 'T-X', 'payment_ids' => [], 'state' => 'open',
    ]);
}

function contractAdjustment(string $state): Adjustment
{
    return Adjustment::query()->create([
        'exception_id' => contractException()->id, 'type' => 'write_off', 'amount' => '10.00', 'reason' => 'Contract test',
        'proposed_by' => demoUser('admin@demo')->id, 'state' => $state, 'high_value' => false, 'idempotency_key' => (string) Str::uuid(),
        'journal' => ['posting_date' => '2026-09-22', 'reference' => 'contract', 'lines' => [
            ['account' => '6150-BAD-DEBT-WRITE-OFF', 'debit' => '10.00', 'credit' => '0.00', 'transaction_id' => null],
            ['account' => '1100-CUSTOMER-RECEIVABLES', 'debit' => '0.00', 'credit' => '10.00', 'transaction_id' => 'T-X'],
        ]],
    ]);
}

function contractSuggestion(): AiSuggestion
{
    return AiSuggestion::query()->create([
        'kind' => 'triage', 'exception_id' => contractException()->id, 'status' => 'pending', 'model' => 'stub-rules', 'prompt_version' => 'v1',
        'prompt_hash' => str_repeat('c', 64), 'input' => [], 'input_hash' => str_repeat('d', 64),
        'output' => ['likely_cause' => 'other', 'recommended_action' => 'investigate', 'explanation' => 'x', 'evidence' => [], 'confidence' => 0.5],
    ]);
}

function contractUrl(string $name, array $params, $user): string
{
    $resolved = array_map(fn (string $v) => match (true) {
        $v === 'spare' => Role::query()->create(['name' => 'spare_'.uniqid(), 'guard_name' => 'web', 'label' => 'Spare'])->id,
        $v === 'self' => $user->id,
        $v === 'staging' => contractStaging($user)->id,
        $v === 'batch' => contractBatch()->id,
        $v === 'run' => ReconRun::query()->create(['business_date' => '2026-09-22', 'version' => random_int(1, 1000000), 'status' => 'queued', 'trigger' => 'manual', 'rule_config' => []])->id,
        $v === 'result' => contractResult()->id,
        $v === 'exception' => contractException()->id,
        $v === 'adjustment' => contractAdjustment('pending_approval')->id,
        $v === 'failed_adjustment' => contractAdjustment('posting_failed')->id,
        $v === 'suggestion' => contractSuggestion()->id,
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
