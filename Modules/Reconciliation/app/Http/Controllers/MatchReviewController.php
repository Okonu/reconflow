<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reconciliation\Http\Requests\ConfirmMatchesRequest;
use Modules\Reconciliation\Http\Requests\RejectMatchRequest;
use Modules\Reconciliation\Http\Resources\FuzzyMatchResource;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\MatchReviewService;

final class MatchReviewController extends Controller
{
    public function index(Request $request, MatchReviewService $reviews): Response
    {
        $this->authorize('viewResults', ReconRun::class);
        $date = $request->string('date')->toString() ?: null;

        return Inertia::render('Reconciliation/Matches/Index', [
            'matches' => FuzzyMatchResource::collection($reviews->pending($date)->paginate(100)->withQueryString()),
            'filters' => ['date' => $date],
            'can' => ['review' => $request->user()?->can('confirmMatch', new ReconResult) ?? false],
        ]);
    }

    public function confirm(ConfirmMatchesRequest $request, MatchReviewService $reviews): RedirectResponse
    {
        $count = $reviews->confirm($request->user(), $request->resultIds(), (string) ($request->validated('reason') ?? ''));

        return back()->with('success', "Confirmed {$count} fuzzy matches.");
    }

    public function reject(RejectMatchRequest $request, ReconResult $result, MatchReviewService $reviews): RedirectResponse
    {
        $reviews->reject($request->user(), $result, (string) $request->validated('reason'));

        return back()->with('success', 'Match rejected: the sale and the payment are now separate exceptions.');
    }
}
