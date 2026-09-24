<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Ingestion\Enums\SourceType;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Enums\RunTrigger;
use Modules\Reconciliation\Models\ReconRun;

final class RunNowRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', ReconRun::class);
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'refresh' => ['boolean'],
            'replace_manual' => ['array'],
            'replace_manual.*' => [Rule::enum(SourceType::class)],
        ];
    }

    public function toRunRequest(): RunRequest
    {
        return new RunRequest(
            businessDate: (string) $this->validated('business_date'),
            trigger: RunTrigger::Manual,
            refreshFromSources: $this->boolean('refresh'),
            replaceManualSources: $this->boolean('refresh') ? array_values((array) $this->validated('replace_manual', [])) : [],
        );
    }
}
