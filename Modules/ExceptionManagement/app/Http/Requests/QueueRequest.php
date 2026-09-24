<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\ExceptionManagement\DTOs\QueueFilters;
use Modules\ExceptionManagement\Models\ReconException;

final class QueueRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', ReconException::class);
    }

    public function rules(): array
    {
        return [
            'state' => ['nullable', 'string', 'max:24'],
            'category' => ['nullable', 'string', 'max:64'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'owner' => ['nullable', 'string', 'max:20'],
            'overdue' => ['nullable', 'boolean'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function filters(): QueueFilters
    {
        return new QueueFilters(
            state: $this->validated('state') ?? 'open',
            category: $this->validated('category'),
            severity: $this->validated('severity'),
            owner: $this->validated('owner'),
            overdue: $this->boolean('overdue'),
            businessDate: $this->validated('business_date'),
            search: $this->validated('search'),
        );
    }
}
