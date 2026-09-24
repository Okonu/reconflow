<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Controllers;

use App\Contracts\ExceptionDetailContributor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ExceptionManagement\Enums\ExceptionCategory;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Http\Requests\QueueRequest;
use Modules\ExceptionManagement\Http\Resources\ExceptionResource;
use Modules\ExceptionManagement\Models\ExceptionEvent;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Services\QueueSummary;
use Modules\ExceptionManagement\Services\SourceRecords;
use Modules\Reconciliation\Support\RuleExplanation;

final class ExceptionController extends Controller
{
    public function index(QueueRequest $request, QueueSummary $summary): Response
    {
        $filters = $request->filters();
        $user = $request->user();

        return Inertia::render('ExceptionManagement/Exceptions/Index', [
            'exceptions' => ExceptionResource::collection(ReconException::query()->queue($filters, (int) $user?->id)->paginate(50)->withQueryString()),
            'filters' => $filters->toArray(),
            'summary' => $summary->counts(),
            'options' => [
                'categories' => array_column(ExceptionCategory::cases(), 'value'),
                'states' => array_map(fn (ExceptionState $s) => ['value' => $s->value, 'label' => $s->label()], ExceptionState::cases()),
                'owners' => $summary->owners(),
            ],
            'can' => ['assign' => $user?->can('assign', ReconException::class) ?? false],
        ]);
    }

    public function show(Request $request, ReconException $exception, SourceRecords $records): Response
    {
        $this->authorize('view', $exception);
        $user = $request->user();
        $exception->load(['owner', 'result.run']);
        $contributions = [];
        foreach (app()->tagged(ExceptionDetailContributor::TAG) as $contributor) {
            if ($contributor instanceof ExceptionDetailContributor && $user !== null) {
                $contributions[$contributor->key()] = $contributor->contribute($exception, $user);
            }
        }
        $result = $exception->resultRecord();

        return Inertia::render('ExceptionManagement/Exceptions/Show', [
            'exception' => new ExceptionResource($exception),
            'result' => $result === null ? null : [
                'id' => $result->id,
                'status' => $result->status->value,
                'rule_id' => $result->rule_id,
                'explanation' => RuleExplanation::explain($result->status, $result->rule_id, (array) $result->flags, $result->tag),
                'expected_amount' => $result->expected_amount === null ? null : (string) $result->expected_amount,
                'actual_amount' => $result->actual_amount === null ? null : (string) $result->actual_amount,
                'posted_amount' => $result->posted_amount === null ? null : (string) $result->posted_amount,
                'variance' => $result->variance === null ? null : (string) $result->variance,
                'variance_pct' => $result->variance_pct,
                'run_id' => $result->run_id,
                'run_version' => $result->runRecord()?->version,
            ],
            'records' => $records->for($exception),
            'timeline' => ExceptionEvent::query()->where('exception_id', $exception->id)->orderBy('id')->get()->map(fn (ExceptionEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'actor' => $e->actor_label,
                'from' => $e->from_state,
                'to' => $e->to_state,
                'comment' => $e->comment,
                'at' => $e->created_at?->toIso8601String(),
            ])->all(),
            'contributions' => $contributions,
            'can' => [
                'work' => ($user?->can('work', $exception) ?? false) && ! $exception->state->isClosed(),
                'review' => ($user?->can('work', $exception) ?? false) && $exception->state === ExceptionState::Open,
                'resolve' => ($user?->can('work', $exception) ?? false) && in_array($exception->state, [ExceptionState::Open, ExceptionState::InReview], true),
                'find_matches' => $exception->status->value === 'MISSING_PAYMENT' && ! $exception->state->isClosed() && ($user?->can('matches.confirm') ?? false),
            ],
        ]);
    }
}
