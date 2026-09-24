<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Reconciliation\Models\ReconResult;

final class ConfirmMatchesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('confirmMatch', new ReconResult);
    }

    public function rules(): array
    {
        return [
            'result_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'result_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function resultIds(): array
    {
        return array_map('intval', (array) $this->validated('result_ids'));
    }
}
