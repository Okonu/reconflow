<?php

declare(strict_types=1);

namespace Modules\Adjustments\Services;

use Modules\Adjustments\Contracts\ErpClient;
use Modules\Adjustments\DTOs\ErpResponse;
use Modules\Ingestion\Services\MockErp;
use Throwable;

final class LocalMockErpClient implements ErpClient
{
    public function __construct(private readonly MockErp $erp) {}

    public function postJournal(array $request): ErpResponse
    {
        if ($this->erp->simulatingFailure()) {
            return new ErpResponse(false, 503, [], 'Simulated ERP failure.');
        }
        try {
            return new ErpResponse(true, 200, $this->erp->post($request));
        } catch (Throwable $e) {
            return new ErpResponse(false, 422, [], $e->getMessage());
        }
    }
}
