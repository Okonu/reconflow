<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ExceptionManagement\Actions\ReopenDate;
use Modules\ExceptionManagement\Actions\SignOffDate;
use Modules\ExceptionManagement\Http\Requests\SignoffRequest;
use Modules\ExceptionManagement\Http\Resources\ExceptionResource;
use Modules\ExceptionManagement\Models\RunSignoff;
use Modules\ExceptionManagement\Services\SignoffService;

final class SignoffController extends Controller
{
    public function show(Request $request, string $date, SignoffService $signoffs): Response
    {
        $this->authorize('view', RunSignoff::class);
        $status = $signoffs->status($date);
        $user = $request->user();

        return Inertia::render('ExceptionManagement/Signoff/Show', [
            'date' => $date,
            'run' => $status['run'] === null ? null : ['id' => $status['run']->id, 'version' => $status['run']->version, 'summary' => $status['run']->summary],
            'signoff' => $status['signoff'] === null ? null : [
                'signed_by' => $status['signoff']->signer?->getAttribute('name'),
                'signed_at' => $status['signoff']->signed_at->toIso8601String(),
                'comment' => $status['signoff']->comment,
                'carried' => count($status['signoff']->carried_exception_ids),
            ],
            'blockers' => $status['blockers'],
            'blocking_exceptions' => ExceptionResource::collection($status['blocking_exceptions']),
            'to_acknowledge' => ExceptionResource::collection($status['to_acknowledge']),
            'pending_fuzzy' => $status['pending_fuzzy'],
            'can' => [
                'sign' => $status['can_sign'] && ($user?->can('create', RunSignoff::class) ?? false),
                'reopen' => $status['signoff'] !== null && ($user?->can('reopen', RunSignoff::class) ?? false),
            ],
        ]);
    }

    public function store(SignoffRequest $request, string $date, SignOffDate $signOff): RedirectResponse
    {
        $signOff->handle($request->user(), $date, $request->text());

        return back()->with('success', "{$date} signed off. Its results are locked.");
    }

    public function reopen(SignoffRequest $request, string $date, ReopenDate $reopen): RedirectResponse
    {
        $reopen->handle($request->user(), $date, $request->text());

        return back()->with('success', "{$date} reopened.");
    }
}
