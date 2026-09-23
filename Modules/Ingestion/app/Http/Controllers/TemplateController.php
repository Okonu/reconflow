<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Services\SamplePackBuilder;
use Modules\Ingestion\Services\TemplateBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class TemplateController extends Controller
{
    public function template(SourceType $source, TemplateBuilder $templates): BinaryFileResponse
    {
        $this->authorize('viewAny', UploadStaging::class);

        return response()->download($templates->build($source), $source->templateFileName())->deleteFileAfterSend();
    }

    public function samplePack(SamplePackBuilder $builder): BinaryFileResponse
    {
        $this->authorize('viewAny', UploadStaging::class);

        return response()->download($builder->build(), 'reconflow-sample-pack.zip')->deleteFileAfterSend();
    }
}
