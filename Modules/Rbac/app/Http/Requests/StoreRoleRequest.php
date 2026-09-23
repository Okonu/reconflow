<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Rbac\DTOs\RoleData;
use Modules\Rbac\Models\Role;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', Role::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'min:2', 'max:128'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct'],
        ];
    }

    public function toData(): RoleData
    {
        return new RoleData(
            code: (string) $this->validated('code'),
            label: (string) $this->validated('label'),
            description: (string) ($this->validated('description') ?? ''),
            permissions: array_values((array) $this->validated('permissions')),
        );
    }
}
