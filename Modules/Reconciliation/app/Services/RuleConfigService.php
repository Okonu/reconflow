<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use App\Contracts\AuditActor;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Reconciliation\DTOs\RuleConfig;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Models\RuleConfigVersion;

final class RuleConfigService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function current(): RuleConfig
    {
        $latest = RuleConfigVersion::query()->orderByDesc('version')->first();
        if ($latest === null) {
            return RuleConfig::fromArray($this->defaults());
        }

        return RuleConfig::fromArray([...$this->defaults(), ...$latest->values], $latest->id, $latest->version);
    }

    public function defaults(): array
    {
        return [
            ...(array) config('reconciliation.defaults'),
            'grace_hours' => (int) config('reconflow.payments_window_grace_hours'),
            'timezone' => (string) config('reconflow.display_timezone'),
        ];
    }

    public function create(array $values, AuditActor|string|null $actor, string $comment = ''): RuleConfigVersion
    {
        return DB::transaction(function () use ($values, $actor, $comment): RuleConfigVersion {
            $previous = RuleConfigVersion::query()->orderByDesc('version')->lockForUpdate()->first();
            $merged = RuleConfig::fromArray([...$this->defaults(), ...($previous->values ?? []), ...$values])->toArray();
            unset($merged['version']);
            $version = RuleConfigVersion::query()->create([
                'version' => ($previous->version ?? 0) + 1,
                'values' => $merged,
                'comment' => $comment,
                'created_by' => $actor instanceof AuditActor ? $actor->auditActorId() : null,
            ]);
            $this->audit->record(ReconAuditAction::RuleConfigCreated, $actor, 'rule_config', $version->id, [
                'version' => $version->version,
                'values' => $merged,
                'previous' => $previous?->values,
                'comment' => $comment,
            ]);

            return $version;
        });
    }

    public function ensureSeeded(): RuleConfigVersion
    {
        return RuleConfigVersion::query()->orderByDesc('version')->first() ?? $this->create([], AuditLogger::SYSTEM_ACTOR, 'Initial defaults');
    }
}
