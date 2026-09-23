<?php

declare(strict_types=1);

namespace Modules\Ingestion\Connectors;

use App\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Ingestion\DTOs\SourceExtract;
use Modules\Ingestion\Enums\SourceType;

final class MockSourceApiConnector implements SourceConnector
{
    public function fetch(SourceType $source, string $businessDate): SourceExtract
    {
        $baseUrl = rtrim((string) config('ingestion.sources.base_url'), '/');

        try {
            $response = Http::acceptJson()
                ->withToken((string) config('ingestion.sources.token'))
                ->timeout((int) config('ingestion.sources.timeout_seconds'))
                ->retry(2, 500, throw: false)
                ->get("{$baseUrl}/api/mock/{$source->value}", ['date' => $businessDate]);
        } catch (ConnectionException $e) {
            throw DomainException::conflict("The {$source->label()} source system is unreachable: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw DomainException::conflict("The {$source->label()} source system returned HTTP {$response->status()}.");
        }

        return new SourceExtract(
            source: $source,
            businessDate: $businessDate,
            rows: (array) $response->json('rows', []),
            extractedAt: CarbonImmutable::parse((string) $response->json('extracted_at')),
            system: (string) $response->json('system', 'mock'),
        );
    }
}
