<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Contracts\SettingsSection;
use Illuminate\Contracts\Container\Container;

final class SettingsSections
{
    public function __construct(private readonly Container $app) {}

    public function all(): array
    {
        $sections = [];
        foreach ($this->app->tagged(SettingsSection::TAG) as $section) {
            if ($section instanceof SettingsSection) {
                $sections[$section->key()] = $section;
            }
        }
        uasort($sections, fn (SettingsSection $a, SettingsSection $b): int => $a->order() <=> $b->order());

        return $sections;
    }

    public function find(string $key): ?SettingsSection
    {
        return $this->all()[$key] ?? null;
    }
}
