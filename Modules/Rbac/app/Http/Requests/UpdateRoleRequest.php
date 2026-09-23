<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Rbac\DTOs\RoleChanges;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('role'));
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'min:2', 'max:128'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct'],
        ];
    }

    public function toChanges(): RoleChanges
    {
        return new RoleChanges(
            label: $this->validated('label'),
            description: $this->has('description') ? (string) ($this->validated('description') ?? '') : null,
            permissions: $this->has('permissions') ? array_values((array) $this->validated('permissions')) : null,
        );
    }
}
