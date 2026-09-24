<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Reconciliation\Models\ReconRun;

final class ListRunsRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', ReconRun::class);
    }

    public function rules(): array
    {
        return ['date' => ['nullable', 'date_format:Y-m-d']];
    }

    public function selectedDate(string $default): string
    {
        return (string) ($this->validated('date') ?? $default);
    }
}
