<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Contracts\LlmClient;
use Modules\AI\DTOs\LlmRequest;
use Modules\AI\DTOs\LlmResult;
use Modules\AI\Enums\LikelyCause;
use Modules\AI\Enums\RecommendedAction;

final class StubLlmClient implements LlmClient
{
    public function name(): string
    {
        return 'stub-rules';
    }

    public function available(): bool
    {
        return true;
    }

    public function complete(LlmRequest $request): LlmResult
    {
        $context = $request->context;
        $output = isset($context['exception'])
            ? $this->triage($context)
            : [
                'headline' => 'Offline summary for '.($context['business_date'] ?? 'the date'),
                'paragraphs' => [sprintf('Match rate was %s%% across %s items.', $context['run']['match_rate'] ?? 'n/a', $context['run']['items'] ?? 0)],
                'watch_items' => [],
            ];

        return new LlmResult($output, $this->name(), 0, 0);
    }

    private function triage(array $context): array
    {
        $status = (string) ($context['exception']['status'] ?? '');
        $variance = (float) ($context['result']['variance'] ?? 0);
        [$cause, $action] = match ($status) {
            'VARIANCE' => $variance < 0 ? [LikelyCause::CustomerUnderpaid, RecommendedAction::ContactCustomer] : [LikelyCause::CustomerOverpaid, RecommendedAction::Refund],
            'MISSING_PAYMENT' => [LikelyCause::PaymentNotYetReceived, RecommendedAction::ContactCustomer],
            'PENDING_TIMING' => [LikelyCause::TimingDifference, RecommendedAction::WaitForPayment],
            'UNMATCHED_PAYMENT' => [LikelyCause::UnidentifiedReceipt, RecommendedAction::MoveToSuspense],
            'DUPLICATE_PAYMENT' => [LikelyCause::DuplicatePayment, RecommendedAction::Refund],
            'POSTING_MISMATCH' => [LikelyCause::ErpPostingWrongAmount, RecommendedAction::CorrectPosting],
            'MISSING_POSTING' => [LikelyCause::ErpPostingMissing, RecommendedAction::PostMissing],
            'DUPLICATE_POSTING' => [LikelyCause::DuplicateErpPosting, RecommendedAction::ReverseDuplicatePosting],
            default => [LikelyCause::Other, RecommendedAction::Investigate],
        };

        return [
            'likely_cause' => $cause->value,
            'recommended_action' => $action->value,
            'explanation' => 'Rule-based offline stub: mapped from the reconciliation status only.',
            'evidence' => ["Status {$status}"],
            'confidence' => 0.5,
        ];
    }
}
