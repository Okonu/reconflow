<?php

declare(strict_types=1);

namespace Modules\AI\Actions;

use App\Exceptions\DomainException;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Enums\RecommendedAction;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiSuggestion;
use Modules\Audit\Services\AuditLogger;
use Modules\Users\Models\User;

final class DecideSuggestion
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function accept(User $user, AiSuggestion $suggestion): AiSuggestion
    {
        $this->assertPending($suggestion);
        $suggestion->update(['status' => SuggestionStatus::Accepted, 'decided_by' => $user->id, 'decided_at' => now()]);
        $this->audit->record(AiAuditAction::SuggestionAccepted, $user, 'ai_suggestion', $suggestion->id, [
            'exception_id' => $suggestion->exception_id,
            'recommended_action' => $suggestion->output['recommended_action'] ?? null,
        ]);

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

        return $suggestion;
    }

    private function assertPending(AiSuggestion $suggestion): void
    {
        if ($suggestion->status !== SuggestionStatus::Pending) {
            throw DomainException::conflict('This suggestion has already been decided.');
        }
    }
}
