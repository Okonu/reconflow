<?php

declare(strict_types=1);

namespace Modules\AI\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\AI\Enums\RecommendedAction;

final class DecideSuggestionRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('decide', $this->route('suggestion'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,override'],
            'action' => ['nullable', Rule::enum(RecommendedAction::class)],
            'reason' => ['required_if:decision,override', 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function overriding(): bool
    {
        return $this->validated('decision') === 'override';
    }

    public function action(): ?RecommendedAction
    {
        return RecommendedAction::tryFrom((string) $this->validated('action'));
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
