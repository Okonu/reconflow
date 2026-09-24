<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\ExceptionManagement\Models\RunSignoff;

final class SignoffRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect($this->routeIs('signoff.reopen') ? 'reopen' : 'create', RunSignoff::class);
    }

    public function rules(): array
    {
        return $this->routeIs('signoff.reopen')
            ? ['reason' => ['required', 'string', 'min:5', 'max:2000']]
            : ['comment' => ['nullable', 'string', 'max:2000']];
    }

    public function text(): string
    {
        return trim((string) ($this->validated('reason') ?? $this->validated('comment') ?? ''));
    }
}
