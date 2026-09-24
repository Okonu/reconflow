<?php

declare(strict_types=1);

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Dashboard\Enums\DashboardPermission;
use Modules\Dashboard\Services\DashboardData;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $data): Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $canView = $request->user()?->can(DashboardPermission::View->value) ?? false;

        return Inertia::render('Dashboard/Index', [
            'dashboard' => $canView ? $data->build($request->string('date')->toString() ?: null) : null,
        ]);
    }
}
