<?php

declare(strict_types=1);

namespace Modules\AI\Support;

use Modules\AI\Enums\LikelyCause;
use Modules\AI\Enums\RecommendedAction;

final class Schemas
{
    public static function triage(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'likely_cause' => ['type' => 'string', 'enum' => array_column(LikelyCause::cases(), 'value')],
                'recommended_action' => ['type' => 'string', 'enum' => array_column(RecommendedAction::cases(), 'value')],
                'explanation' => ['type' => 'string'],
                'evidence' => ['type' => 'array', 'items' => ['type' => 'string']],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['likely_cause', 'recommended_action', 'explanation', 'evidence', 'confidence'],
            'additionalProperties' => false,
        ];
    }

    public static function runSummary(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']],
                'watch_items' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['headline', 'paragraphs', 'watch_items'],
            'additionalProperties' => false,
        ];
    }

    public static function validTriage(array $output): bool
    {
        return LikelyCause::tryFrom((string) ($output['likely_cause'] ?? '')) !== null
            && RecommendedAction::tryFrom((string) ($output['recommended_action'] ?? '')) !== null
            && is_string($output['explanation'] ?? null)
            && is_array($output['evidence'] ?? null)
            && is_numeric($output['confidence'] ?? null);
    }

    public static function validRunSummary(array $output): bool
    {
        return is_string($output['headline'] ?? null) && is_array($output['paragraphs'] ?? null) && is_array($output['watch_items'] ?? null);
    }
}
