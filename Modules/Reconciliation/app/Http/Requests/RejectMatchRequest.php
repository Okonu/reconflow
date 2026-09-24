<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RejectMatchRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('confirmMatch', $this->route('result'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
