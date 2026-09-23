<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;
use Modules\Ingestion\Services\TemplateBuilder;
use RuntimeException;

final class ExportSeededDay
{
    public function __construct(
        private readonly MockSourceStore $store,
        private readonly TemplateBuilder $templates,
    ) {}

    public function handle(string $businessDate, string $directory): array
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }
        $files = [];
        foreach (SourceType::cases() as $source) {
            $path = $this->templates->build($source, $this->store->extract($source, $businessDate));
            $target = rtrim($directory, '/')."/{$source->exportFilePrefix()}_{$businessDate}.xlsx";
            rename($path, $target);
            $files[] = $target;
        }

        return $files;
    }
}
