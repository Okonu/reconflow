<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reconciliation\DTOs\PossibleMatch;

final class PossibleMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $match = $this->resource;
        assert($match instanceof PossibleMatch);

        return [
            'payment_result_id' => $match->paymentResultId,
            'payment_id' => $match->paymentId,
            'payment_date' => $match->paymentDate,
            'paid_at' => $match->paidAt,
            'amount' => $match->amount,
            'reference' => $match->reference,
            'days_late' => $match->daysLate,
        ];
    }
}
