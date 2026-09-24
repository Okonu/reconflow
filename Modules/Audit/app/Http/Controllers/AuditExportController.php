<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Export\SpreadsheetSafe;
use Modules\Audit\Enums\AuditAction;
use Modules\Audit\Http\Requests\ListAuditEventsRequest;
use Modules\Audit\Models\AuditEvent;
use Modules\Audit\Services\AuditLogger;
use Modules\DataProtection\Support\PersonalData;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditExportController extends Controller
{
    public function __invoke(ListAuditEventsRequest $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->filters();
        $query = AuditEvent::query()->filtered($filters)->orderBy('id');
        $audit->record(AuditAction::Exported, $request->user(), 'audit_event', null, ['filters' => $filters->toArray(), 'rows' => (clone $query)->count()]);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'occurred_at', 'actor', 'action', 'entity_type', 'entity_id', 'request_id', 'payload', 'prev_hash', 'hash'], escape: '');
            foreach ($query->lazyById(1000) as $event) {
                fputcsv($out, SpreadsheetSafe::row([
                    $event->id,
                    $event->occurred_at->toIso8601String(),
                    $event->actor_label,
                    $event->action,
                    $event->entity_type,
                    $event->entity_id,
                    $event->request_id,
                    json_encode(PersonalData::scrub($event->payload), JSON_UNESCAPED_SLASHES),
                    $event->prev_hash,
                    $event->hash,
                ]), escape: '');
            }
            fclose($out);
        }, 'audit_log_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
