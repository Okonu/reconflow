<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Http\Requests\ListAuditEventsRequest;
use Modules\Audit\Http\Resources\AuditEventResource;
use Modules\Audit\Models\AuditEvent;

final class AuditLogController extends Controller
{
    public function index(ListAuditEventsRequest $request): Response
    {
        $filters = $request->filters();

        $events = AuditEvent::query()
            ->filtered($filters)
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('Audit/Index', [
            'events' => AuditEventResource::collection($events),
            'filters' => $filters->toArray(),
            'can' => [
                'verify' => $request->user()?->can('verify', AuditEvent::class) ?? false,
                'export' => $request->user()?->can('export', AuditEvent::class) ?? false,
            ],
        ]);
    }
}
