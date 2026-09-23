<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Modules\Users\DTOs\NewUserData;
use Modules\Users\Models\User;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'max:254', 'regex:/^[^@\s]+@[^@\s]+$/'],
            'password' => ['required', 'string', 'max:256', Password::defaults()],
            'region' => ['nullable', 'string', 'max:64'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function toData(): NewUserData
    {
        return new NewUserData(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            password: (string) $this->validated('password'),
            region: $this->validated('region'),
            roleIds: array_map('intval', (array) $this->validated('role_ids')),
        );
    }
}
