<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\AI\Contracts\LlmClient;
use Modules\AI\Models\AiEvalRun;
use Modules\AI\Models\AiSuggestion;
use Modules\Audit\Models\AuditEvent;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Models\PaymentRecord;
use Modules\Ingestion\Models\SalesRecord;
use Modules\Reconciliation\Models\ReconRun;

beforeEach(function (): void {
    importAnswerKeyFiles('golden');
    reconcile();
});

it('never sends a raw phone number to the model, only pseudonym tokens', function (): void {
    $fake = fakeLlm();
    $exception = exceptionFor('TUP-S-000041');

    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage', $exception))->assertSessionHas('success');

    $payload = $fake->requests[0]['user'];
    $phones = SalesRecord::query()->pluck('customer_phone')->merge(PaymentRecord::query()->whereNotNull('payer_phone')->pluck('payer_phone'))->unique();
    foreach ($phones as $phone) {
        expect($payload)->not->toContain($phone)->not->toContain('0'.substr((string) $phone, 3));
    }
    expect($payload)->toContain('CUST_')
        ->and(preg_match('/(?<!\d)(?:254|0)7\d{8}(?!\d)/', $payload))->toBe(0);

    $suggestion = AiSuggestion::query()->sole();
    expect($suggestion->input_hash)->toBe(hash('sha256', json_encode(json_decode($payload, true), JSON_UNESCAPED_SLASHES)))
        ->and($suggestion->prompt_version)->toBe('v1')
        ->and($suggestion->prompt_hash)->toBe(hash('sha256', (string) file_get_contents(module_path('AI', 'resources/prompts/v1_triage.md'))))
        ->and($suggestion->status->value)->toBe('pending');
});

it('records acceptance and requires a reason to override, without touching workflow tables', function (): void {
    fakeLlm();
    $exception = exceptionFor('TUP-S-000041');
    $events = DB::table('exception_events')->count();
    $state = $exception->state;
    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage', $exception));
    $suggestion = AiSuggestion::query()->sole();

    $this->post(route('ai.decide', $suggestion), ['decision' => 'override'])->assertSessionHasErrors('reason');
    $this->post(route('ai.decide', $suggestion), ['decision' => 'override', 'action' => 'contact_customer', 'reason' => 'Customer already promised the balance'])->assertSessionHas('success');

    expect($suggestion->fresh()->status->value)->toBe('overridden')
        ->and($suggestion->fresh()->override_action)->toBe('contact_customer')
        ->and($suggestion->fresh()->decision_reason)->toBe('Customer already promised the balance')
        ->and(DB::table('exception_events')->count())->toBe($events)
        ->and($exception->fresh()->state)->toBe($state)
        ->and(DB::table('adjustments')->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'ai.suggestion_overridden')->exists())->toBeTrue();

    $this->post(route('ai.decide', $suggestion), ['decision' => 'accept'])->assertForbidden();
});

it('rejects model output that does not match the schema and records the failure', function (): void {
    fakeLlm(['likely_cause' => 'aliens', 'recommended_action' => 'write_off', 'explanation' => 'x', 'evidence' => [], 'confidence' => 1]);

    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage', exceptionFor('TUP-S-000041')))->assertSessionHas('error');

    expect(AiSuggestion::query()->sole()->status->value)->toBe('failed')
        ->and(AuditEvent::query()->where('action', 'ai.suggestion_failed')->exists())->toBeTrue();
});

it('stops all AI calls when the kill switch is on and keeps rules-only categorisation', function (): void {
    $fake = fakeLlm();
    $this->actingAs(demoUser('admin@demo'))->post(route('ai.kill-switch'))->assertSessionHas('success');

    $exception = exceptionFor('TUP-S-000041');
    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage', $exception))->assertSessionHas('error');
    $this->get(route('exceptions.show', $exception))->assertOk()
        ->assertInertia(fn ($page) => $page->where('contributions.ai.enabled', false)->where('exception.data.category', 'Customer under/over-payment'));

    expect($fake->requests)->toBe([])
        ->and(AuditEvent::query()->where('action', 'ai.kill_switch_toggled')->value('payload'))->toMatchArray(['kill_switch' => true]);
});

it('explains that AI is unavailable when no API key is configured', function (): void {
    config(['ai.driver' => 'claude', 'ai.api_key' => null]);
    app()->forgetInstance(LlmClient::class);

    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage', exceptionFor('TUP-S-000041')))
        ->assertSessionHas('error', 'AI suggestion unavailable: No Anthropic API key is configured.');
});

it('builds the run narrative from aggregates only', function (): void {
    $fake = fakeLlm(['headline' => 'Good day', 'paragraphs' => ['p'], 'watch_items' => []]);
    $run = ReconRun::query()->latestCompleted()->firstOrFail();

    $this->actingAs(demoUser('manager@demo'))->postJson(route('ai.summary.store', $run))->assertCreated()->assertJsonPath('summary.output.headline', 'Good day');

    $sent = json_decode($fake->requests[0]['user'], true);
    expect(array_keys($sent))->toBe(['business_date', 'run', 'open_exceptions'])
        ->and($fake->requests[0]['user'])->not->toContain('TUP-S-');
});

it('queues triage for every open exception of a date that has no suggestion yet', function (): void {
    $fake = fakeLlm();

    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage-batch'), ['business_date' => '2026-09-22'])->assertSessionHas('success');

    expect(AiSuggestion::query()->count())->toBe(ReconException::query()->open()->count())
        ->and(count($fake->requests))->toBe(AiSuggestion::query()->count());
});

it('scores the eval set with the offline stub', function (): void {
    $this->artisan('reconflow:ai-eval', ['--stub' => true])->assertSuccessful();

    $run = AiEvalRun::query()->sole();
    expect($run->cases)->toBe(20)->and($run->errors)->toBe(0)->and($run->action_correct)->toBeGreaterThanOrEqual(15);
});

it('shows oversight metrics to auditors but not the kill switch', function (): void {
    fakeLlm();
    $this->actingAs(demoUser('analyst@demo'))->post(route('ai.triage', exceptionFor('TUP-S-000041')));
    $this->post(route('ai.decide', AiSuggestion::query()->sole()), ['decision' => 'accept']);

    $this->actingAs(demoUser('auditor@demo'))->get(route('ai.oversight'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('AI/Oversight/Index')
            ->where('stats.triage.accepted', 1)
            ->where('stats.triage.acceptance_rate', 100)
            ->where('can.manage', false));
    $this->post(route('ai.kill-switch'))->assertForbidden();
});
