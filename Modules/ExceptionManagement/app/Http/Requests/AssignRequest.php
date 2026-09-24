<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\ExceptionManagement\Models\ReconException;

final class AssignRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('assign', ReconException::class);
    }

    public function rules(): array
    {
        return [
            'exception_ids' => ['required', 'array', 'min:1', 'max:500'],
            'exception_ids.*' => ['integer'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
