<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Contracts\LlmClient;
use Modules\AI\Models\AiSetting;

final class AiSettings
{
    public const KILL_SWITCH = 'kill_switch';

    public function __construct(private readonly LlmClient $client) {}

    public function killed(): bool
    {
        return (bool) (AiSetting::query()->find(self::KILL_SWITCH)?->value['on'] ?? false);
    }

    public function enabled(): bool
    {
        return (bool) config('ai.enabled') && ! $this->killed() && $this->client->available();
    }

    public function status(): array
    {
        $setting = AiSetting::query()->find(self::KILL_SWITCH);

        return [
            'enabled' => $this->enabled(),
            'configured' => (bool) config('ai.enabled'),
            'killed' => $this->killed(),
            'client_available' => $this->client->available(),
            'model' => $this->client->name(),
            'reason' => match (true) {
                ! config('ai.enabled') => 'The AI assistant is disabled in configuration (AI_ENABLED).',
                $this->killed() => 'The AI assistant has been switched off with the kill switch.',
                ! $this->client->available() => 'No Anthropic API key is configured.',
                default => null,
            },
            'toggled_by' => $setting?->value['by_name'] ?? null,
            'toggled_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }

    public function setKilled(bool $on, int $userId, string $userName): void
    {
        AiSetting::query()->updateOrCreate(['key' => self::KILL_SWITCH], ['value' => ['on' => $on, 'by' => $userId, 'by_name' => $userName]]);
    }
}
