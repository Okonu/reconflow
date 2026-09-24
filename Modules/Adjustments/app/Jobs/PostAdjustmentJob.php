<?php

declare(strict_types=1);

namespace Modules\Adjustments\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Adjustments\Actions\PostAdjustment;
use Modules\Adjustments\Models\Adjustment;

final class PostAdjustmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $adjustmentId) {}

    public function handle(PostAdjustment $post): void
    {
        $adjustment = Adjustment::query()->find($this->adjustmentId);
        if ($adjustment !== null) {
            $post->handle($adjustment);
        }
    }
}
