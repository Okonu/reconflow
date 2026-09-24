<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Contracts\LlmClient;
use Modules\AI\DTOs\LlmRequest;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiSuggestion;
use Modules\Audit\Services\AuditLogger;
use Modules\Users\Models\User;
use Throwable;

final class SuggestionRecorder
{
    public function __construct(
        private readonly LlmClient $client,
        private readonly PromptRepository $prompts,
        private readonly AiSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function record(User $user, SuggestionKind $kind, array $context, array $schema, callable $valid, array $links, SuggestionStatus $successStatus): AiSuggestion
    {
        if (! $this->settings->enabled()) {
            throw new AiUnavailable((string) ($this->settings->status()['reason'] ?? 'The AI assistant is unavailable.'));
        }
        $prompt = $this->prompts->load($kind);
        $started = hrtime(true);
        $attributes = [
            'kind' => $kind,
            'requested_by' => $user->id,
            'model' => $this->client->name(),
            'prompt_version' => $prompt['version'],
            'prompt_hash' => $prompt['hash'],
            'input' => $context,
            'input_hash' => hash('sha256', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ...$links,
        ];

        try {
            $result = $this->client->complete(new LlmRequest($prompt['text'], $context, $schema));
            if (! $valid($result->output)) {
                throw new AiUnavailable('The model output did not match the expected schema.');
            }
            $suggestion = AiSuggestion::query()->create([
                ...$attributes,
                'status' => $successStatus,
                'model' => $result->model,
                'output' => $result->output,
                'served_by_fallback' => $result->servedByFallback,
                'latency_ms' => intdiv(hrtime(true) - $started, 1_000_000),
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);
            $this->audit->record(AiAuditAction::SuggestionCreated, $user, 'ai_suggestion', $suggestion->id, [
                'kind' => $kind->value, 'model' => $suggestion->model, 'prompt_version' => $prompt['version'], ...$links,
            ]);

            return $suggestion;
        } catch (Throwable $e) {
            $message = $e instanceof AiUnavailable ? $e->getMessage() : 'Unexpected error while calling the AI service.';
            $failed = AiSuggestion::query()->create([
                ...$attributes,
                'status' => SuggestionStatus::Failed,
                'error' => mb_substr($message, 0, 500),
                'latency_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            ]);
            $this->audit->record(AiAuditAction::SuggestionFailed, $user, 'ai_suggestion', $failed->id, ['kind' => $kind->value, 'error' => $message, ...$links]);
            report($e);

            throw new AiUnavailable($message, previous: $e);
        }
    }
}
