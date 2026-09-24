<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CommentRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('work', $this->route('exception'));
    }

    public function rules(): array
    {
        return ['comment' => explode('|', 'required|string|min:1|max:5000')];
    }

    public function text(): string
    {
        return trim((string) $this->validated('comment'));
    }
}
