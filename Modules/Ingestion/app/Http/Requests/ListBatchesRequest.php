<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SourceBatch;

final class ListBatchesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', SourceBatch::class);
    }

    public function rules(): array
    {
        return [
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', Rule::enum(SourceType::class)],
            'include_superseded' => ['nullable', 'boolean'],
        ];
    }

    public function filters(): array
    {
        return [
            'business_date' => $this->validated('business_date'),
            'source' => $this->validated('source'),
            'include_superseded' => $this->boolean('include_superseded'),
        ];
    }
}
