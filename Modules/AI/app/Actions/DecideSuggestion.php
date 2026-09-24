<?php

declare(strict_types=1);

namespace Modules\AI\Actions;

use App\Exceptions\DomainException;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Enums\RecommendedAction;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiSuggestion;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Services\ExceptionWorkflow;
use Modules\Users\Models\User;

final class DecideSuggestion
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ExceptionWorkflow $workflow,
    ) {}

    public function accept(User $user, AiSuggestion $suggestion): AiSuggestion
    {
        $this->assertPending($suggestion);
        $suggestion->update(['status' => SuggestionStatus::Accepted, 'decided_by' => $user->id, 'decided_at' => now()]);
        $this->audit->record(AiAuditAction::SuggestionAccepted, $user, 'ai_suggestion', $suggestion->id, [
            'exception_id' => $suggestion->exception_id,
            'recommended_action' => $suggestion->output['recommended_action'] ?? null,
        ]);
        $this->note($suggestion, $user, 'Accepted the AI suggestion: '.$this->actionLabel($suggestion->output['recommended_action'] ?? null));

        return $suggestion;
    }

    public function override(User $user, AiSuggestion $suggestion, ?RecommendedAction $action, string $reason): AiSuggestion
    {
        $this->assertPending($suggestion);
        $suggestion->update([
            'status' => SuggestionStatus::Overridden,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'override_action' => $action?->value,
            'decision_reason' => $reason,
        ]);
        $this->audit->record(AiAuditAction::SuggestionOverridden, $user, 'ai_suggestion', $suggestion->id, [
            'exception_id' => $suggestion->exception_id,
            'recommended_action' => $suggestion->output['recommended_action'] ?? null,
            'override_action' => $action?->value,
            'reason' => $reason,
        ]);
        $this->note($suggestion, $user, 'Overrode the AI suggestion'.($action === null ? '' : " (chose: {$action->label()})").": {$reason}");

        return $suggestion;
    }

    private function assertPending(AiSuggestion $suggestion): void
    {
        if ($suggestion->status !== SuggestionStatus::Pending) {
            throw DomainException::conflict('This suggestion has already been decided.');
        }
    }

    private function note(AiSuggestion $suggestion, User $user, string $comment): void
    {
        $exception = $suggestion->exception_id === null ? null : ReconException::query()->find($suggestion->exception_id);
        if ($exception !== null) {
            $this->workflow->event($exception, $user, 'ai_decision', $comment);
        }
    }

    private function actionLabel(?string $value): string
    {
        return RecommendedAction::tryFrom((string) $value)?->label() ?? 'unknown';
    }
}
