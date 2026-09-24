<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Reconciliation\Actions\ConfirmManualMatch;
use Modules\Reconciliation\Http\Requests\ConfirmManualMatchRequest;
use Modules\Reconciliation\Http\Resources\PossibleMatchResource;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Services\PossibleMatchFinder;

final class PossibleMatchController extends Controller
{
    public function index(ReconResult $result, PossibleMatchFinder $finder): AnonymousResourceCollection
    {
        $this->authorize('view', $result);

        return PossibleMatchResource::collection($finder->candidatesFor($result));
    }

    public function store(ConfirmManualMatchRequest $request, ReconResult $result, ConfirmManualMatch $confirm): RedirectResponse|JsonResponse
    {
        $match = $confirm->handle($request->user(), $result, $request->paymentResultId(), $request->reason());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['manual_match_id' => $match->id], 201);
        }

        return back()->with('success', "Matched {$match->transaction_id} to payment {$match->payment_id}.");
    }
}
