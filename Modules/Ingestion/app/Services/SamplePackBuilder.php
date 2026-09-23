<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

final class SamplePackBuilder
{
    public function build(): string
    {
        $root = rtrim((string) config('ingestion.samples_path'), '/');
        $path = tempnam(sys_get_temp_dir(), 'pack').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the sample pack');
        }
        foreach (['golden', 'templates'] as $folder) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$folder}", RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                assert($file instanceof SplFileInfo);
                $zip->addFile($file->getPathname(), 'reconflow-sample-pack/'.substr($file->getPathname(), strlen($root) + 1));
            }
        }
        $zip->close();

        return $path;
    }
}
