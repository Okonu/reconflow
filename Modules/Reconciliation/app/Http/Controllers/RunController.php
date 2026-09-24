<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\BusinessCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reconciliation\Actions\QueueRun;
use Modules\Reconciliation\Http\Requests\ListRunsRequest;
use Modules\Reconciliation\Http\Requests\RunNowRequest;
use Modules\Reconciliation\Http\Resources\ReconRunResource;
use Modules\Reconciliation\Jobs\ExecuteRunJob;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\SourceReadiness;

final class RunController extends Controller
{
    public function index(ListRunsRequest $request, SourceReadiness $readiness): Response
    {
        $default = BusinessCalendar::latestClosedDate();

        return Inertia::render('Reconciliation/Runs/Index', [
            'runs' => ReconRunResource::collection(ReconRun::query()->history()->paginate(30)->withQueryString()),
            'default_business_date' => $default,
            'readiness' => $readiness->forDate($request->selectedDate($default)),
            'can' => ['trigger' => $request->user()?->can('create', ReconRun::class) ?? false],
        ]);
    }

    public function store(RunNowRequest $request, QueueRun $queue): RedirectResponse
    {
        $runRequest = $request->toRunRequest();
        $run = $queue->handle($runRequest, $request->user());
        ExecuteRunJob::dispatch($run->id, $runRequest, $request->user()?->id);

        return redirect()->route('runs.show', $run);
    }

    public function show(Request $request, ReconRun $run): Response
    {
        $this->authorize('view', $run);

        return Inertia::render('Reconciliation/Runs/Show', [
            'run' => new ReconRunResource($run->load('triggeredBy')),
        ]);
    }
}
