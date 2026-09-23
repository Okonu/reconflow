<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Ingestion\Models\SourceBatch;

final class ResetDemoRequest extends FormRequest
{
    public const CONFIRMATION = 'RESET';

    public function authorize(): Response
    {
        return Gate::inspect('resetDemo', SourceBatch::class);
    }

    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', 'in:'.self::CONFIRMATION]];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Type '.self::CONFIRMATION.' to confirm.'];
    }
}
