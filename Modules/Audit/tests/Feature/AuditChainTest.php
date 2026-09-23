<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Models\AuditEvent;
use Modules\Audit\Services\AuditArchiver;
use Modules\Audit\Services\AuditLogger;
use Modules\Audit\Services\ChainVerifier;

function writeEvents(int $count): void
{
    foreach (range(1, $count) as $i) {
        app(AuditLogger::class)->record('test.event', entityType: 'thing', entityId: $i, payload: ['i' => $i]);
    }
}

function tamper(string $sql, array $bindings = []): void
{
    DB::statement('set local session_replication_role = replica');
    DB::statement($sql, $bindings);
    DB::statement('set local session_replication_role = origin');
}

function events(): array
{
    return DB::table('audit_events')->orderBy('id')->get()->all();
}

it('links every event to the previous one from genesis', function (): void {
    writeEvents(3);
    $events = events();

    expect($events[0]->prev_hash)->toBe(AuditEvent::GENESIS_HASH);
    foreach (array_slice($events, 1) as $i => $event) {
        expect($event->prev_hash)->toBe($events[$i]->hash);
    }
    $result = app(ChainVerifier::class)->verify();
    expect($result->ok)->toBeTrue()
        ->and($result->anchor)->toBe('genesis')
        ->and($result->eventsChecked)->toBe(count($events))
        ->and($result->headHash)->toBe(end($events)->hash);
});

it('scrubs personal data from event payloads before hashing', function (): void {
    app(AuditLogger::class)->record('test.pii', payload: ['note' => 'customer 254700123456 called']);

    expect(json_encode(AuditEvent::query()->latest('id')->first()->payload))->not->toContain('254700123456')
        ->and(app(ChainVerifier::class)->verify()->ok)->toBeTrue();
});

it('rejects update, delete and truncate at the database level', function (string $sql): void {
    writeEvents(1);

    expect(fn () => DB::transaction(fn () => DB::statement($sql)))
        ->toThrow(QueryException::class, 'append-only');
})->with([
    "update audit_events set action = 'x'",
    'delete from audit_events',
    'truncate audit_events',
]);

it('rejects updates and deletes through the model', function (): void {
    writeEvents(1);
    $event = AuditEvent::query()->first();

    expect(fn () => $event->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('detects an edited event', function (): void {
    writeEvents(5);
    $target = events()[2];
    tamper("update audit_events set payload = '{\"i\": 999}'::jsonb where id = ?", [$target->id]);

    $result = app(ChainVerifier::class)->verify();
    expect($result->ok)->toBeFalse()
        ->and($result->brokenAtId)->toBe($target->id)
        ->and($result->reason)->toContain('altered');
});

it('detects an edit whose hash was recomputed by breaking the next link', function (): void {
    writeEvents(5);
    $target = events()[2];
    tamper('update audit_events set hash = ? where id = ?', [str_repeat('f', 64), $target->id]);

    $result = app(ChainVerifier::class)->verify();
    expect($result->ok)->toBeFalse()->and($result->brokenAtId)->toBe($target->id);
});

it('detects a deleted event', function (): void {
    writeEvents(5);
    $victim = events()[3];
    tamper('delete from audit_events where id = ?', [$victim->id]);

    $result = app(ChainVerifier::class)->verify();
    expect($result->ok)->toBeFalse()
        ->and($result->brokenAtId)->toBe($victim->id + 1)
        ->and($result->reason)->toContain('Link broken');
});

it('detects removal of the start of the chain without a checkpoint', function (): void {
    writeEvents(2);
    tamper('delete from audit_events where id = ?', [events()[0]->id]);

    $result = app(ChainVerifier::class)->verify();
    expect($result->ok)->toBeFalse()->and($result->reason)->toContain('not anchored');
});

it('archives expired events behind a checkpoint and keeps the chain verifiable', function (): void {
    writeEvents(4);
    $before = events();
    $directory = sys_get_temp_dir().'/audit-archive-'.uniqid();

    $result = app(AuditArchiver::class)->archiveExpired(7, $directory, CarbonImmutable::now()->addYears(7)->addMonth());

    expect($result->archivedCount)->toBe(count($before))
        ->and(is_file((string) $result->file))->toBeTrue();
    $remaining = events();
    expect($remaining)->toHaveCount(1);
    $checkpoint = json_decode($remaining[0]->payload, true);
    expect($remaining[0]->action)->toBe('retention.audit_archive')
        ->and($checkpoint['last_archived_hash'])->toBe(end($before)->hash)
        ->and($checkpoint['file_sha256'])->toBe($result->fileSha256);

    writeEvents(2);
    $chain = app(ChainVerifier::class)->verify();
    expect($chain->ok)->toBeTrue()->and($chain->anchor)->toStartWith('checkpoint:');

    $archived = app(AuditArchiver::class)->verifyArchiveFile((string) $result->file, $result->fileSha256);
    expect($archived->ok)->toBeTrue()->and($archived->eventsChecked)->toBe(count($before));
});

it('records a no-op archive run when nothing has expired', function (): void {
    writeEvents(2);
    $result = app(AuditArchiver::class)->archiveExpired(7, sys_get_temp_dir().'/unused-'.uniqid());

    expect($result->archivedCount)->toBe(0)
        ->and(AuditEvent::query()->latest('id')->first()->payload['archived_count'])->toBe(0)
        ->and(app(ChainVerifier::class)->verify()->ok)->toBeTrue();
});

it('detects a tampered archive file', function (): void {
    writeEvents(2);
    $result = app(AuditArchiver::class)->archiveExpired(7, sys_get_temp_dir().'/audit-archive-'.uniqid(), CarbonImmutable::now()->addYears(9));
    file_put_contents((string) $result->file, 'tampered', FILE_APPEND);

    expect(app(AuditArchiver::class)->verifyArchiveFile((string) $result->file, $result->fileSha256)->ok)->toBeFalse();
});

it('serialises chain writes with a transaction-scoped advisory lock', function (): void {
    DB::transaction(function (): void {
        AuditLogger::lockChain();
        $held = DB::table('pg_locks')->where('locktype', 'advisory')->where('granted', true)->where('pid', DB::raw('pg_backend_pid()'))->count();
        expect($held)->toBeGreaterThanOrEqual(1);
    });
});
