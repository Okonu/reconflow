<?php

declare(strict_types=1);

namespace App\Contracts;

interface SettingsSection
{
    public const TAG = 'reconflow.settings';

    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function order(): int;

    public function viewPermission(): string;

    public function managePermission(): string;

    public function fields(): array;

    public function rules(): array;

    public function values(): array;

    public function history(): array;

    public function save(array $values, AuditActor $actor, string $comment): void;
}
