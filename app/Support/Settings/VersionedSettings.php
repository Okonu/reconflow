<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Support\Facades\DB;

final class VersionedSettings
{
    private array $cache = [];

    public function current(string $section, array $defaults): array
    {
        $latest = $this->cache[$section] ??= SettingVersion::query()->where('section', $section)->orderByDesc('version')->first()->values ?? [];

        return [...$defaults, ...array_intersect_key($latest, $defaults)];
    }

    public function store(string $section, array $values, ?int $actorId, string $comment): array
    {
        $result = DB::transaction(function () use ($section, $values, $actorId, $comment): array {
            $previous = SettingVersion::query()->where('section', $section)->orderByDesc('version')->lockForUpdate()->first();
            $version = SettingVersion::query()->create([
                'section' => $section,
                'version' => ($previous->version ?? 0) + 1,
                'values' => $values,
                'comment' => $comment,
                'created_by' => $actorId,
            ]);

            return ['version' => $version->version, 'previous' => $previous?->values];
        });
        unset($this->cache[$section]);

        return $result;
    }

    public function history(string $section, int $limit = 10): array
    {
        return SettingVersion::query()->where('section', $section)->orderByDesc('version')->limit($limit)
            ->leftJoin('users', 'users.id', '=', 'setting_versions.created_by')
            ->get(['setting_versions.version', 'setting_versions.values', 'setting_versions.comment', 'setting_versions.created_at', 'users.name as by'])
            ->map(fn (SettingVersion $v): array => [
                'version' => $v->version,
                'values' => $v->values,
                'comment' => $v->comment,
                'by' => $v->getAttribute('by'),
                'at' => $v->created_at?->toIso8601String(),
            ])->all();
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
