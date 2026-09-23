<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class PreviewUploadRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('staging'));
    }

    public function rules(): array
    {
        return [
            'invalid_only' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function invalidOnly(): bool
    {
        return $this->boolean('invalid_only');
    }

    public function pageNumber(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
