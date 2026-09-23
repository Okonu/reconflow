<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support;

use Modules\Ingestion\Support\Schema\SourceSchema;

final class HeaderCheck
{
    public static function compare(SourceSchema $schema, array $headers): array
    {
        $expected = $schema->headers();
        $present = array_values(array_filter($headers, fn (string $h): bool => $h !== ''));
        $missing = array_values(array_diff($expected, $present));
        $unexpected = array_values(array_diff($present, $expected));
        $duplicated = array_values(array_unique(array_diff_assoc($present, array_unique($present))));

        $problems = [];
        if ($missing !== []) {
            $problems[] = 'Missing columns: '.implode(', ', $missing);
        }
        if ($unexpected !== []) {
            $problems[] = 'Unexpected columns: '.implode(', ', $unexpected);
        }
        if ($duplicated !== []) {
            $problems[] = 'Repeated columns: '.implode(', ', $duplicated);
        }

        return [
            'ok' => $problems === [],
            'expected' => $expected,
            'found' => $present,
            'missing' => $missing,
            'unexpected' => $unexpected,
            'duplicated' => $duplicated,
            'message' => $problems === []
                ? 'Columns match the template.'
                : implode('. ', $problems).'. Use the downloadable template and do not rename, add or remove columns.',
        ];
    }
}
