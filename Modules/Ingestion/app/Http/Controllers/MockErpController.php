<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ingestion\Services\MockErp;
use RuntimeException;

final class MockErpController
{
    public function __invoke(Request $request, MockErp $erp): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:100'],
            'posting_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['required', 'string', 'max:128'],
            'reverse_journal_id' => ['nullable', 'string', 'max:64'],
            'lines' => ['present', 'array'],
            'lines.*.account' => ['required', 'string', 'max:64'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.transaction_id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($erp->simulatingFailure()) {
            return response()->json(['error' => ['code' => 'erp_unavailable', 'message' => 'Simulated ERP failure.']], 503);
        }

        try {
            return response()->json($erp->post($validated));
        } catch (RuntimeException $e) {
            return response()->json(['error' => ['code' => 'invalid_journal', 'message' => $e->getMessage()]], 422);
        }
    }
}
