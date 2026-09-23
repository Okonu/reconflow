<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Users\DTOs\UserChanges;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toChanges(): UserChanges
    {
        return new UserChanges(
            name: $this->validated('name'),
            region: $this->validated('region'),
            isActive: $this->has('is_active') ? $this->boolean('is_active') : null,
        );
    }
}
