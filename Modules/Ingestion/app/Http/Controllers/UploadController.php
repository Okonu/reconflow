<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\BusinessCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ingestion\Actions\CancelUpload;
use Modules\Ingestion\Actions\ConfirmUpload;
use Modules\Ingestion\Actions\StageUpload;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Http\Requests\ConfirmUploadRequest;
use Modules\Ingestion\Http\Requests\PreviewUploadRequest;
use Modules\Ingestion\Http\Requests\StageUploadRequest;
use Modules\Ingestion\Http\Resources\PreviewRowResource;
use Modules\Ingestion\Http\Resources\UploadStagingResource;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Services\StagedUploadPreview;

final class UploadController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UploadStaging::class);
        $user = $request->user();

        return Inertia::render('Ingestion/Uploads/Index', [
            'sources' => array_map(fn (SourceType $s): array => ['value' => $s->value, 'label' => $s->label()], SourceType::cases()),
            'history' => UploadStagingResource::collection(UploadStaging::query()->history()->get()),
            'default_business_date' => BusinessCalendar::latestClosedDate(),
            'can' => [
                'upload' => $user?->can('create', UploadStaging::class) ?? false,
                'reset' => $user?->can('resetDemo', SourceBatch::class) ?? false,
            ],
        ]);
    }

    public function store(StageUploadRequest $request, StageUpload $stage): RedirectResponse
    {
        $staging = $stage->handle($request->user(), $request->source(), $request->businessDate(), $request->upload());

        return redirect()->route('ingestion.uploads.show', $staging);
    }

    public function show(PreviewUploadRequest $request, UploadStaging $staging, StagedUploadPreview $preview): Response
    {
        $page = $preview->page($staging, $request->invalidOnly(), $request->pageNumber(), (int) config('ingestion.upload.preview_page_size'));
        $dataset = $staging->source->inventoryDataset();

        return Inertia::render('Ingestion/Uploads/Preview', [
            'upload' => new UploadStagingResource($staging->load('uploader')),
            'columns' => $staging->source->schema()->headers(),
            'rows' => array_map(fn ($row) => (new PreviewRowResource($row, $dataset))->resolve($request), $page['rows']),
            'pagination' => [
                'page' => $page['page'],
                'last_page' => $page['last_page'],
                'total' => $page['total'],
                'per_page' => $page['per_page'],
            ],
            'append_conflicts' => $page['append_conflicts'],
            'filters' => ['invalid_only' => $request->invalidOnly()],
        ]);
    }

    public function confirm(ConfirmUploadRequest $request, UploadStaging $staging, ConfirmUpload $confirm): RedirectResponse
    {
        $batch = $confirm->handle($request->user(), $staging, $request->mode());

        return redirect()->route('ingestion.batches.show', $batch)
            ->with('success', "Imported {$batch->rows_loaded} rows; {$batch->rows_quarantined} quarantined.");
    }

    public function cancel(Request $request, UploadStaging $staging, CancelUpload $cancel): RedirectResponse
    {
        $this->authorize('cancel', $staging);
        $cancel->handle($request->user(), $staging);

        return redirect()->route('ingestion.uploads.index')->with('success', 'Upload cancelled; nothing was imported.');
    }
}
