<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\UploadStaging;

final class StageUploadRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', UploadStaging::class);
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::enum(SourceType::class)],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'max:'.(int) config('ingestion.upload.max_kilobytes')],
        ];
    }

    public function source(): SourceType
    {
        return SourceType::from((string) $this->validated('source'));
    }

    public function businessDate(): string
    {
        return (string) $this->validated('business_date');
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');
        assert($file instanceof UploadedFile);

        return $file;
    }
}
