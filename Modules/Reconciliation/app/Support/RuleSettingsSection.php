<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Support;

use App\Contracts\AuditActor;
use App\Contracts\SettingsSection;
use Modules\Reconciliation\Enums\ReconPermission;
use Modules\Reconciliation\Models\RuleConfigVersion;
use Modules\Reconciliation\Services\RuleConfigService;

final class RuleSettingsSection implements SettingsSection
{
    private const FIELDS = ['tolerance', 'fuzzy_window_hours', 'timing_cutoff', 'duplicate_window_minutes', 'timing_carry_days', 'late_payment_lookback_days', 'schedule_time', 'blocked_retry_minutes', 'blocked_max_attempts'];

    public function __construct(private readonly RuleConfigService $config) {}

    public function key(): string
    {
        return 'rules';
    }

    public function label(): string
    {
        return 'Matching rules';
    }

    public function description(): string
    {
        return 'Applies to runs started after the change. Each run records the rule version it used.';
    }

    public function order(): int
    {
        return 10;
    }

    public function viewPermission(): string
    {
        return ReconPermission::ViewConfig->value;
    }

    public function managePermission(): string
    {
        return ReconPermission::ManageConfig->value;
    }

    public function fields(): array
    {
        return [
            ['name' => 'tolerance', 'label' => 'Amount tolerance (USD)', 'type' => 'money', 'help' => 'Differences up to this amount, or 0.5% if greater, count as a match.'],
            ['name' => 'fuzzy_window_hours', 'label' => 'Fuzzy match window (hours)', 'type' => 'number', 'help' => 'How far apart a sale and a reference-less payment may be.'],
            ['name' => 'timing_cutoff', 'label' => 'Timing cut-off', 'type' => 'time', 'help' => 'Unpaid sales after this time are timing items rather than missing payments.'],
            ['name' => 'duplicate_window_minutes', 'label' => 'Duplicate payment window (minutes)', 'type' => 'number'],
            ['name' => 'timing_carry_days', 'label' => 'Timing carry-forward (days)', 'type' => 'number'],
            ['name' => 'late_payment_lookback_days', 'label' => 'Late payment lookback (days)', 'type' => 'number'],
            ['name' => 'schedule_time', 'label' => 'Daily run time (Africa/Nairobi)', 'type' => 'time'],
            ['name' => 'blocked_retry_minutes', 'label' => 'Retry interval when data is missing (minutes)', 'type' => 'number'],
            ['name' => 'blocked_max_attempts', 'label' => 'Maximum retries when data is missing', 'type' => 'number'],
        ];
    }

    public function rules(): array
    {
        return [
            'tolerance' => ['required', 'regex:/^\d{1,4}(\.\d{1,2})?$/'],
            'fuzzy_window_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'timing_cutoff' => ['required', 'date_format:H:i'],
            'duplicate_window_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'timing_carry_days' => ['required', 'integer', 'min:1', 'max:7'],
            'late_payment_lookback_days' => ['required', 'integer', 'min:1', 'max:31'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'blocked_retry_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'blocked_max_attempts' => ['required', 'integer', 'min:1', 'max:48'],
        ];
    }

    public function values(): array
    {
        return array_intersect_key($this->config->current()->toArray(), array_flip(self::FIELDS));
    }

    public function history(): array
    {
        return RuleConfigVersion::query()->leftJoin('users', 'users.id', '=', 'recon_rule_configs.created_by')
            ->orderByDesc('recon_rule_configs.version')->limit(10)
            ->get(['recon_rule_configs.version', 'recon_rule_configs.values', 'recon_rule_configs.comment', 'recon_rule_configs.created_at', 'users.name as by'])
            ->map(fn (RuleConfigVersion $v): array => [
                'version' => $v->version,
                'values' => array_intersect_key((array) $v->values, array_flip(self::FIELDS)),
                'comment' => $v->comment,
                'by' => $v->getAttribute('by') ?? 'System',
                'at' => $v->created_at?->toIso8601String(),
            ])->all();
    }

    public function save(array $values, AuditActor $actor, string $comment): void
    {
        $this->config->create(array_intersect_key($values, array_flip(self::FIELDS)), $actor, $comment);
    }
}
