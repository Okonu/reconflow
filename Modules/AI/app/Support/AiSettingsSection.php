<?php

declare(strict_types=1);

namespace Modules\AI\Support;

use App\Contracts\AuditActor;
use App\Contracts\SettingsSection;
use App\Support\Settings\VersionedSettings;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Enums\AiPermission;
use Modules\AI\Services\AiConfig;
use Modules\AI\Services\AiSettings;
use Modules\Audit\Services\AuditLogger;

final class AiSettingsSection implements SettingsSection
{
    public function __construct(
        private readonly AiConfig $config,
        private readonly AiSettings $ai,
        private readonly VersionedSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function key(): string
    {
        return AiConfig::SECTION;
    }

    public function label(): string
    {
        return 'AI assistant';
    }

    public function description(): string
    {
        return 'The assistant only suggests; people decide. Switching it off falls back to rules-only categorisation with no loss of function.';
    }

    public function order(): int
    {
        return 40;
    }

    public function viewPermission(): string
    {
        return AiPermission::Oversee->value;
    }

    public function managePermission(): string
    {
        return AiPermission::Manage->value;
    }

    public function fields(): array
    {
        return [
            ['name' => 'enabled', 'label' => 'AI assistant on', 'type' => 'boolean', 'help' => 'Same as the kill switch on the AI oversight page.'],
            ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'help' => 'Anthropic model ID, for example claude-opus-5.'],
            ['name' => 'effort', 'label' => 'Reasoning effort', 'type' => 'select', 'options' => AiConfig::EFFORTS],
            ['name' => 'owner_role', 'label' => 'Accountable AI owner (role)', 'type' => 'text'],
            ['name' => 'last_review_date', 'label' => 'Last AI review', 'type' => 'date'],
        ];
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'model' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9.\-]+$/'],
            'effort' => ['required', 'in:'.implode(',', AiConfig::EFFORTS)],
            'owner_role' => ['required', 'string', 'max:100'],
            'last_review_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function values(): array
    {
        return ['enabled' => ! $this->ai->killed(), ...$this->config->all()];
    }

    public function history(): array
    {
        return $this->settings->history($this->key());
    }

    public function save(array $values, AuditActor $actor, string $comment): void
    {
        $enabled = (bool) ($values['enabled'] ?? true);
        if ($enabled === $this->ai->killed()) {
            $this->ai->setKilled(! $enabled, (int) $actor->auditActorId(), $actor->auditActorLabel());
            $this->audit->record(AiAuditAction::KillSwitchToggled, $actor, 'ai_settings', null, ['kill_switch' => ! $enabled, 'via' => 'settings']);
        }
        $values = array_intersect_key([...$values, 'last_review_date' => (string) ($values['last_review_date'] ?? '')], $this->config->defaults());
        $stored = $this->settings->store($this->key(), $values, $actor->auditActorId(), $comment);
        $this->audit->record(AiAuditAction::SettingsChanged, $actor, 'settings', null, ['section' => $this->key(), 'version' => $stored['version'], 'values' => $values, 'previous' => $stored['previous'], 'comment' => $comment]);
    }
}
