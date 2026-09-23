<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Users\DTOs\Credentials;

final class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', 'regex:/^[^@\s]+@[^@\s]+$/'],
            'password' => ['required', 'string', 'max:256'],
        ];
    }

    public function credentials(): Credentials
    {
        return new Credentials((string) $this->validated('email'), (string) $this->validated('password'), (string) $this->ip());
    }
}
