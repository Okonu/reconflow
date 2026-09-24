<?php

declare(strict_types=1);

namespace Modules\DataProtection\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class EraseSubjectRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('erasePersonalData');
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,13}$/'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'request_reference' => ['required', 'string', 'max:100'],
        ];
    }
}
