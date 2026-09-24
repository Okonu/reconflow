<?php

declare(strict_types=1);

namespace Modules\Adjustments\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Adjustments\DTOs\AdjustmentProposal;
use Modules\Adjustments\Enums\AdjustmentType;
use Modules\Adjustments\Models\Adjustment;

final class ProposeAdjustmentRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('propose', Adjustment::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function proposal(): AdjustmentProposal
    {
        return new AdjustmentProposal(AdjustmentType::from((string) $this->validated('type')), (string) $this->validated('amount'), trim((string) $this->validated('reason')));
    }
}
