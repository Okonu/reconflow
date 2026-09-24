<?php

declare(strict_types=1);

namespace Modules\Adjustments\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Adjustments\Contracts\ErpClient;
use Modules\Adjustments\DTOs\ErpResponse;

final class HttpMockErpClient implements ErpClient
{
    public function postJournal(array $request): ErpResponse
    {
        try {
            $response = Http::acceptJson()
                ->withToken((string) config('ingestion.sources.token'))
                ->timeout((int) config('ingestion.sources.timeout_seconds'))
                ->withHeaders(['Idempotency-Key' => (string) $request['idempotency_key']])
                ->post(rtrim((string) config('ingestion.sources.base_url'), '/').'/api/mock/erp/journals', $request);
        } catch (ConnectionException $e) {
            return new ErpResponse(false, 0, [], 'ERP unreachable: '.$e->getMessage());
        }

        return new ErpResponse($response->successful(), $response->status(), (array) $response->json(), $response->successful() ? null : (string) $response->json('error.message', 'ERP returned HTTP '.$response->status()));
    }
}
