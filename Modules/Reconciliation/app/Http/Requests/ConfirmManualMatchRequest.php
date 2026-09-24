<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConfirmManualMatchRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('confirmMatch', $this->route('result'));
    }

    public function rules(): array
    {
        return [
            'payment_result_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function paymentResultId(): int
    {
        return (int) $this->validated('payment_result_id');
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
