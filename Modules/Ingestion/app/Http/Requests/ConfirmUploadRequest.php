<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Ingestion\Enums\ImportMode;

final class ConfirmUploadRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('confirm', $this->route('staging'));
    }

    public function rules(): array
    {
        return ['mode' => ['nullable', Rule::enum(ImportMode::class)]];
    }

    public function mode(): ?ImportMode
    {
        $mode = $this->validated('mode');

        return $mode === null ? null : ImportMode::from((string) $mode);
    }
}
