<?php

declare(strict_types=1);

namespace Modules\Adjustments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Adjustments\Actions\DecideAdjustment;
use Modules\Adjustments\Actions\PostAdjustment;
use Modules\Adjustments\Actions\ProposeAdjustment;
use Modules\Adjustments\Enums\AdjustmentAuditAction;
use Modules\Adjustments\Http\Requests\DecisionRequest;
use Modules\Adjustments\Http\Requests\ProposeAdjustmentRequest;
use Modules\Adjustments\Http\Resources\AdjustmentResource;
use Modules\Adjustments\Models\Adjustment;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Services\MockErp;

final class AdjustmentController extends Controller
{
    public function index(Request $request, MockErp $erp): Response
    {
        $this->authorize('viewAny', Adjustment::class);

        return Inertia::render('Adjustments/Approvals/Index', [
            'pending' => AdjustmentResource::collection(Adjustment::query()->with(['proposer', 'exception'])->pendingApproval()->oldest('id')->get()),
            'recent' => AdjustmentResource::collection(Adjustment::query()->with(['proposer', 'decider', 'exception'])->whereNot('state', 'pending_approval')->latest('updated_at')->limit(25)->get()),
            'erp' => [
                'simulating_failure' => $erp->simulatingFailure(),
                'can_manage' => $request->user()?->can('manageErp', Adjustment::class) ?? false,
            ],
        ]);
    }

    public function store(ProposeAdjustmentRequest $request, ReconException $exception, ProposeAdjustment $propose): RedirectResponse
    {
        $adjustment = $propose->handle($request->user(), $exception, $request->proposal());

        return back()->with('success', "Adjustment proposed for approval (idempotency key {$adjustment->idempotency_key}).");
    }

    public function approve(DecisionRequest $request, Adjustment $adjustment, DecideAdjustment $decide): RedirectResponse
    {
        $decide->approve($request->user(), $adjustment, $request->comment());
        $fresh = $adjustment->fresh();

        return back()->with('success', $fresh?->erp_journal_id ? "Approved and posted to the ERP as {$fresh->erp_journal_id}." : 'Approved. Posting to the ERP…');
    }

    public function reject(DecisionRequest $request, Adjustment $adjustment, DecideAdjustment $decide): RedirectResponse
    {
        $decide->reject($request->user(), $adjustment, $request->comment());

        return back()->with('success', 'Adjustment rejected; the exception is back in review.');
    }

    public function retry(Request $request, Adjustment $adjustment, PostAdjustment $post): RedirectResponse
    {
        $this->authorize('retry', $adjustment);
        $result = $post->handle($adjustment);

        return back()->with($result->erp_journal_id ? 'success' : 'error', $result->erp_journal_id ? "Posted to the ERP as {$result->erp_journal_id}." : 'The ERP is still unavailable.');
    }

    public function toggleErpFailure(Request $request, MockErp $erp, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manageErp', Adjustment::class);
        $on = ! $erp->simulatingFailure();
        $erp->setFailureSimulation($on);
        $audit->record(AdjustmentAuditAction::ErpFailureToggled, $request->user(), 'mock_erp', null, ['simulating_failure' => $on]);

        return back()->with('success', $on ? 'The simulated ERP will now reject postings.' : 'The simulated ERP is accepting postings again.');
    }
}
