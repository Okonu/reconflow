<?php

declare(strict_types=1);

namespace Modules\Rbac\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Rbac\Models\Role;

final class AssignRolesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('assign', Role::class);
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function roleIds(): array
    {
        return array_map('intval', (array) $this->validated('role_ids'));
    }
}
