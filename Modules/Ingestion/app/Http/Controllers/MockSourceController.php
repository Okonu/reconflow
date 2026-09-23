<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;
use Modules\Ingestion\Support\Schema\ValidationContext;

final class MockSourceController
{
    public function __invoke(Request $request, SourceType $source, MockSourceStore $store): JsonResponse
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $context = ValidationContext::forDate((string) $validated['date']);

        return response()->json([
            'system' => 'simulated-'.$source->value,
            'simulated' => true,
            'source' => $source->value,
            'business_date' => $context->dateString(),
            'window' => $source === SourceType::Payments
                ? ['from' => $context->windowStart()->toIso8601String(), 'to' => $context->windowEnd()->toIso8601String()]
                : null,
            'extracted_at' => now()->toIso8601String(),
            'rows' => $store->extract($source, $context->dateString()),
        ]);
    }
}
