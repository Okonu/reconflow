<?php

declare(strict_types=1);

namespace Modules\Adjustments\Support;

use App\Contracts\ExceptionDetailContributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Modules\Adjustments\Enums\AdjustmentType;
use Modules\Adjustments\Http\Resources\AdjustmentResource;
use Modules\Adjustments\Models\Adjustment;
use Modules\Adjustments\Services\ApprovalSettings;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Models\ReconException;

final class AdjustmentsContribution implements ExceptionDetailContributor
{
    public function __construct(private readonly ApprovalSettings $approvals) {}

    public function key(): string
    {
        return 'adjustments';
    }

    public function contribute(Model $exception, User $viewer): array
    {
        assert($exception instanceof ReconException);
        $overPaid = $exception->resultRecord()?->variance?->isPositive() ?? false;
        $types = AdjustmentType::for($exception->status, $overPaid);

        return [
            'items' => AdjustmentResource::collection(Adjustment::query()->with(['proposer', 'decider'])->where('exception_id', $exception->id)->latest('id')->get())->resolve(request()),
            'types' => array_map(fn (AdjustmentType $t) => ['value' => $t->value, 'label' => $t->label()], $types),
            'suggested_amount' => (string) $exception->amount_at_risk,
            'threshold' => (string) $this->approvals->threshold(),
            'can_propose' => $types !== [] && in_array($exception->state, [ExceptionState::Open, ExceptionState::InReview], true) && $viewer->can('propose', Adjustment::class),
        ];
    }
}
