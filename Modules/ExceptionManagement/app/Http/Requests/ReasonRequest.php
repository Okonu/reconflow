<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ReasonRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('work', $this->route('exception'));
    }

    public function rules(): array
    {
        return ['reason' => explode('|', 'required|string|min:3|max:2000')];
    }

    public function text(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
