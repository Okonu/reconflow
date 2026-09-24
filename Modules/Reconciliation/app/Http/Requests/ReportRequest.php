<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Requests;

use App\Support\BusinessCalendar;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Reconciliation\DTOs\ReportFilters;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\RollUp;
use Modules\Reconciliation\Models\ReconRun;

final class ReportRequest extends FormRequest
{
    public function authorize(): Response
    {
        return match (true) {
            $this->routeIs('reports.reconciliation.export-unmasked') => Gate::inspect('exportUnmasked', ReconRun::class),
            $this->routeIs('reports.reconciliation.export') => Gate::inspect('export', ReconRun::class),
            default => Gate::inspect('viewResults', ReconRun::class),
        };
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'section' => ['nullable', 'in:current,prior_day,all'],
            'roll_up' => ['nullable', Rule::enum(RollUp::class)],
            'status' => ['nullable', Rule::enum(ReconStatus::class)],
            'search' => ['nullable', 'string', 'max:64'],
            'format' => ['nullable', 'in:csv,xlsx'],
            'reason' => [$this->routeIs('reports.reconciliation.export-unmasked') ? 'required' : 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    public function filters(): ReportFilters
    {
        return new ReportFilters(
            date: (string) ($this->validated('date') ?? BusinessCalendar::latestClosedDate()),
            section: (string) ($this->validated('section') ?? 'current'),
            rollUp: $this->validated('roll_up'),
            status: $this->validated('status'),
            search: $this->validated('search'),
        );
    }

    public function exportFormat(): string
    {
        return (string) ($this->validated('format') ?? 'xlsx');
    }
}
