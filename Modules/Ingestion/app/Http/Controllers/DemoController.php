<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Ingestion\Actions\ResetDemoData;
use Modules\Ingestion\Http\Requests\ResetDemoRequest;

final class DemoController extends Controller
{
    public function reset(ResetDemoRequest $request, ResetDemoData $reset): RedirectResponse
    {
        $reset->handle($request->user());

        return redirect()->route('ingestion.uploads.index')
            ->with('success', 'Demo data cleared. Fresh synthetic data is being generated in the background.');
    }
}
