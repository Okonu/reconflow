<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiSuggestion;
use Modules\AI\Support\Schemas;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Users\Models\User;

final class TriageService
{
    public function __construct(
        private readonly Redactor $redactor,
        private readonly SuggestionRecorder $recorder,
    ) {}

    public function suggest(User $user, ReconException $exception): AiSuggestion
    {
        return $this->recorder->record(
            $user,
            SuggestionKind::Triage,
            $this->redactor->forException($exception),
            Schemas::triage(),
            Schemas::validTriage(...),
            ['exception_id' => $exception->id],
            SuggestionStatus::Pending,
        );
    }
}
