<?php

declare(strict_types=1);

namespace Modules\Audit\Services;

use Illuminate\Support\Facades\DB;
use Modules\Audit\DTOs\AuditRecord;
use Modules\Audit\DTOs\ChainVerification;
use Modules\Audit\Enums\AuditAction;
use Modules\Audit\Models\AuditEvent;
use Modules\Audit\Support\AuditHasher;

final class ChainVerifier
{
    public function verify(): ChainVerification
    {
        $first = DB::table('audit_events')->orderBy('id')->first();
        if ($first === null) {
            return new ChainVerification(true, 0, null, 'empty');
        }
        $first = AuditRecord::fromRow($first);

        $anchor = $this->anchorFor($first);
        if ($anchor === null) {
            return ChainVerification::broken(0, null, 'none', $first->id, 'Chain start is not anchored: the earliest event is neither genesis nor covered by an archive checkpoint');
        }

        return $this->verifySequence(
            DB::table('audit_events')->orderBy('id')->lazyById(2000)->map(AuditRecord::fromRow(...)),
            $anchor[0],
            $anchor[1],
        );
    }

    public function verifySequence(iterable $records, string $expectedPrev, string $anchor): ChainVerification
    {
        $checked = 0;
        $head = null;
        $prev = $expectedPrev;

        foreach ($records as $record) {
            if ($record->prevHash !== $prev) {
                return ChainVerification::broken($checked, $head, $anchor, $record->id, 'Link broken: event does not point to the previous event (an event was removed, inserted or reordered)');
            }
            if (! hash_equals($record->hash, AuditHasher::recompute($record))) {
                return ChainVerification::broken($checked, $head, $anchor, $record->id, "Content altered: the stored hash does not match the event's contents");
            }
            $prev = $record->hash;
            $head = $record->hash;
            $checked++;
        }

        return new ChainVerification(true, $checked, $head, $anchor);
    }

    private function anchorFor(AuditRecord $first): ?array
    {
        if ($first->prevHash === AuditEvent::GENESIS_HASH) {
            return [AuditEvent::GENESIS_HASH, 'genesis'];
        }
        $checkpoint = DB::table('audit_events')
            ->where('action', AuditAction::ArchiveRun->value)
            ->whereRaw("payload->>'last_archived_hash' = ?", [$first->prevHash])
            ->whereRaw("(payload->>'last_archived_id')::bigint < ?", [$first->id])
            ->first();

        return $checkpoint === null ? null : [$first->prevHash, 'checkpoint:'.$checkpoint->id];
    }
}
