<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reconciliation\Models\ReconResult;

final class FuzzyMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        assert($r instanceof ReconResult);

        return [
            'id' => $r->id,
            'business_date' => $r->business_date->toDateString(),
            'section' => $r->section->value,
            'transaction_id' => $r->transaction_id,
            'payment_ids' => $r->payment_ids,
            'expected_amount' => $r->expected_amount === null ? null : (string) $r->expected_amount,
            'actual_amount' => $r->actual_amount === null ? null : (string) $r->actual_amount,
            'confidence' => $r->match_confidence,
            'tag' => $r->tag,
        ];
    }
}
