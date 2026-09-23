<?php

declare(strict_types=1);

namespace Modules\Audit\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\DTOs\ArchiveResult;
use Modules\Audit\DTOs\AuditRecord;
use Modules\Audit\DTOs\ChainVerification;
use Modules\Audit\Enums\AuditAction;
use RuntimeException;

final class AuditArchiver
{
    public function __construct(
        private readonly AuditLogger $logger,
        private readonly ChainVerifier $verifier,
    ) {}

    public function archiveExpired(int $retentionYears, string $directory, ?CarbonImmutable $now = null): ArchiveResult
    {
        $now ??= CarbonImmutable::now('UTC');
        $cutoff = $now->subYears($retentionYears);

        return DB::transaction(function () use ($cutoff, $directory, $now): ArchiveResult {
            AuditLogger::lockChain();
            $lastId = DB::table('audit_events')->where('occurred_at', '<', $cutoff)->max('id');

            if ($lastId === null) {
                $this->logger->record(AuditAction::ArchiveRun, entityType: 'audit_events', payload: [
                    'archived_count' => 0,
                    'cutoff' => $cutoff->toIso8601String(),
                ]);

                return new ArchiveResult(0);
            }

            $records = DB::table('audit_events')->where('id', '<=', $lastId)->orderBy('id')->get()->map(AuditRecord::fromRow(...))->all();
            [$file, $sha256] = $this->write($records, $directory, $now);
            $last = end($records);

            $this->logger->record(AuditAction::ArchiveRun, entityType: 'audit_events', payload: [
                'archived_count' => count($records),
                'cutoff' => $cutoff->toIso8601String(),
                'first_archived_id' => $records[0]->id,
                'last_archived_id' => $last->id,
                'last_archived_hash' => $last->hash,
                'file' => basename($file),
                'file_sha256' => $sha256,
            ]);

            DB::statement("set local reconflow.audit_archive = 'on'");
            $deleted = DB::table('audit_events')->where('id', '<=', $lastId)->delete();
            DB::statement("set local reconflow.audit_archive = 'off'");

            if ($deleted !== count($records)) {
                throw new RuntimeException('Audit archive removed an unexpected number of events');
            }

            return new ArchiveResult($deleted, $file, $sha256, $last->id, $last->hash);
        });
    }

    public function verifyArchiveFile(string $path, ?string $expectedSha256 = null): ChainVerification
    {
        if ($expectedSha256 !== null && ! hash_equals($expectedSha256, (string) hash_file('sha256', $path))) {
            return ChainVerification::broken(0, null, 'file', null, 'Archive file checksum mismatch');
        }
        $lines = array_filter(explode("\n", (string) gzdecode((string) file_get_contents($path))));
        $records = array_map(fn (string $line): AuditRecord => AuditRecord::fromRow(json_decode($line, true, flags: JSON_THROW_ON_ERROR)), $lines);

        if ($records === []) {
            return new ChainVerification(true, 0, null, 'file');
        }

        return $this->verifier->verifySequence(array_values($records), reset($records)->prevHash, 'file:'.basename($path));
    }

    private function write(array $records, string $directory, CarbonImmutable $now): array
    {
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create audit archive directory {$directory}");
        }
        $first = $records[0]->id;
        $last = end($records)->id;
        $target = sprintf('%s/audit-archive-%d-%d-%s.jsonl.gz', rtrim($directory, '/'), $first, $last, $now->format('Ymd\THis\Z'));
        $body = implode('', array_map(fn (AuditRecord $r): string => json_encode($r->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", $records));
        $tmp = $target.'.tmp';
        file_put_contents($tmp, gzencode($body, 9), LOCK_EX);
        rename($tmp, $target);

        return [$target, (string) hash_file('sha256', $target)];
    }
}
