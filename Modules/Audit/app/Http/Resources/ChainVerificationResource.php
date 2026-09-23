<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\DTOs\ChainVerification;

final class ChainVerificationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $result = $this->resource;
        assert($result instanceof ChainVerification);

        return [
            'ok' => $result->ok,
            'events_checked' => $result->eventsChecked,
            'head_hash' => $result->headHash,
            'anchor' => $result->anchor,
            'broken_at_id' => $result->brokenAtId,
            'reason' => $result->reason,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
