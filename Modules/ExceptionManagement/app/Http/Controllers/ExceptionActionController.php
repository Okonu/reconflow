<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ExceptionManagement\Actions\WorkException;
use Modules\ExceptionManagement\Http\Requests\AssignRequest;
use Modules\ExceptionManagement\Http\Requests\CommentRequest;
use Modules\ExceptionManagement\Http\Requests\ReasonRequest;
use Modules\ExceptionManagement\Models\ReconException;

final class ExceptionActionController extends Controller
{
    public function review(Request $request, ReconException $exception, WorkException $work): RedirectResponse
    {
        $this->authorize('work', $exception);
        $work->startReview($request->user(), $exception);

        return back()->with('success', 'Exception is now in review.');
    }

    public function resolve(ReasonRequest $request, ReconException $exception, WorkException $work): RedirectResponse
    {
        $work->resolveNoAction($request->user(), $exception, $request->text());

        return back()->with('success', 'Exception resolved without action.');
    }

    public function comment(CommentRequest $request, ReconException $exception, WorkException $work): RedirectResponse
    {
        $work->comment($request->user(), $exception, $request->text());

        return back();
    }

    public function assign(AssignRequest $request, WorkException $work): RedirectResponse
    {
        $count = $work->assign($request->user(), array_map('intval', (array) $request->validated('exception_ids')), $request->validated('owner_id') === null ? null : (int) $request->validated('owner_id'));

        return back()->with('success', "Assigned {$count} exceptions.");
    }
}
