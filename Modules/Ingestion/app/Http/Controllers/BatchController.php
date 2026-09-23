<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ingestion\Http\Requests\ListBatchesRequest;
use Modules\Ingestion\Http\Resources\QuarantinedRowResource;
use Modules\Ingestion\Http\Resources\SourceBatchResource;
use Modules\Ingestion\Models\SourceBatch;

final class BatchController extends Controller
{
    public function index(ListBatchesRequest $request): Response|JsonResponse
    {
        $filters = $request->filters();
        $batches = SourceBatch::query()->with('creator')->filtered($filters)->paginate(60)->withQueryString();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return SourceBatchResource::collection($batches)->response();
        }

        return Inertia::render('Ingestion/Batches/Index', [
            'batches' => SourceBatchResource::collection($batches),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, SourceBatch $batch): Response|JsonResponse
    {
        $this->authorize('view', $batch);
        $quarantine = $batch->quarantinedRows()->paginate(100)->withQueryString();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'batch' => (new SourceBatchResource($batch->load('creator')))->resolve($request),
                'quarantined_rows' => QuarantinedRowResource::collection($quarantine)->resolve($request),
            ]);
        }

        return Inertia::render('Ingestion/Batches/Show', [
            'batch' => new SourceBatchResource($batch->load('creator')),
            'quarantined' => QuarantinedRowResource::collection($quarantine),
        ]);
    }
}
