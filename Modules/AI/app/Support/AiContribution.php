<?php

declare(strict_types=1);

namespace Modules\AI\Support;

use App\Contracts\ExceptionDetailContributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Modules\AI\Enums\RecommendedAction;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Http\Resources\AiSuggestionResource;
use Modules\AI\Models\AiSuggestion;
use Modules\AI\Services\AiSettings;
use Modules\ExceptionManagement\Models\ReconException;

final class AiContribution implements ExceptionDetailContributor
{
    public function __construct(private readonly AiSettings $settings) {}

    public function key(): string
    {
        return 'ai';
    }

    public function contribute(Model $exception, User $viewer): array
    {
        assert($exception instanceof ReconException);
        $latest = AiSuggestion::query()->with(['requester', 'decider'])->where('kind', SuggestionKind::Triage->value)
            ->where('exception_id', $exception->id)->latest('id')->first();
        $status = $this->settings->status();

        return [
            'enabled' => $status['enabled'],
            'reason' => $status['reason'],
            'model' => $status['model'],
            'latest' => $latest === null ? null : (new AiSuggestionResource($latest))->resolve(request()),
            'actions' => array_map(fn (RecommendedAction $a): array => ['value' => $a->value, 'label' => $a->label()], RecommendedAction::cases()),
            'can_request' => $status['enabled'] && $viewer->can('create', AiSuggestion::class) && ! $exception->state->isClosed(),
        ];
    }
}
