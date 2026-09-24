<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use App\Support\Settings\VersionedSettings;

final class AiConfig
{
    public const SECTION = 'ai';

    public const EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

    public function __construct(private readonly VersionedSettings $settings) {}

    public function defaults(): array
    {
        return [
            'model' => (string) config('ai.model'),
            'effort' => (string) config('ai.effort'),
            'owner_role' => 'Finance Manager',
            'last_review_date' => '',
        ];
    }

    public function all(): array
    {
        return $this->settings->current(self::SECTION, $this->defaults());
    }

    public function model(): string
    {
        return (string) $this->all()['model'];
    }

    public function effort(): string
    {
        $effort = (string) $this->all()['effort'];

        return in_array($effort, self::EFFORTS, true) ? $effort : 'medium';
    }
}
