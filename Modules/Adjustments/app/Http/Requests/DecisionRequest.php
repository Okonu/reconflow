<?php

declare(strict_types=1);

namespace Modules\Adjustments\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class DecisionRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect($this->routeIs('adjustments.reject') ? 'reject' : 'approve', $this->route('adjustment'));
    }

    public function rules(): array
    {
        return ['comment' => $this->routeIs('adjustments.reject') ? ['required', 'string', 'min:3', 'max:2000'] : ['nullable', 'string', 'max:2000']];
    }

    public function comment(): string
    {
        return trim((string) ($this->validated('comment') ?? ''));
    }
}
