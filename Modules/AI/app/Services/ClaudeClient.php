<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use Modules\AI\Contracts\LlmClient;
use Modules\AI\DTOs\LlmRequest;
use Modules\AI\DTOs\LlmResult;

final class ClaudeClient implements LlmClient
{
    private ?Client $client = null;

    public function __construct(private readonly AiConfig $config) {}

    public function name(): string
    {
        return $this->config->model();
    }

    public function available(): bool
    {
        return filled(config('ai.api_key'));
    }

    public function complete(LlmRequest $request): LlmResult
    {
        if (! $this->available()) {
            throw new AiUnavailable('No Anthropic API key is configured.');
        }
        $fallbacks = (bool) config('ai.refusal_fallbacks');

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: (int) config('ai.max_tokens'),
                messages: [['role' => 'user', 'content' => $request->userContent()]],
                model: $this->config->model(),
                fallbacks: $fallbacks ? 'default' : null,
                outputConfig: [
                    'effort' => $this->config->effort(),
                    'format' => ['type' => 'json_schema', 'schema' => $request->schema],
                ],
                system: [['type' => 'text', 'text' => $request->system, 'cacheControl' => ['type' => 'ephemeral']]],
                thinking: ['type' => 'adaptive'],
                betas: $fallbacks ? ['server-side-fallback-2026-07-01'] : null,
            );
        } catch (AuthenticationException) {
            throw new AiUnavailable('The Anthropic API rejected the configured key.');
        } catch (RateLimitException) {
            throw new AiUnavailable('The AI service is rate limited. Try again shortly.');
        } catch (APIConnectionException) {
            throw new AiUnavailable('Could not reach the AI service.');
        } catch (APIStatusException $e) {
            throw new AiUnavailable('The AI service returned an error ('.$e->getCode().').');
        }

        if ($message->stopReason === 'refusal') {
            throw new AiUnavailable('The model declined this request.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new AiUnavailable('The model response was cut off.');
        }

        $text = '';
        $fallbackUsed = false;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
            if ($block->type === 'fallback') {
                $fallbackUsed = true;
            }
        }
        $output = json_decode($text, true);
        if (! is_array($output)) {
            throw new AiUnavailable('The model returned output that is not valid JSON.');
        }

        return new LlmResult(
            $output,
            $message->model,
            $message->usage->inputTokens,
            $message->usage->outputTokens,
            $fallbackUsed,
        );
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: (string) config('ai.api_key'),
            requestOptions: ['timeout' => (float) config('ai.timeout'), 'maxRetries' => (int) config('ai.max_retries')],
        );
    }
}
