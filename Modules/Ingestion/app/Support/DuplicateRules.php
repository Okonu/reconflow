<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support;

use Modules\Ingestion\Support\Schema\Reason;
use Modules\Ingestion\Support\Schema\RowResult;
use Modules\Ingestion\Support\Schema\SourceSchema;

final class DuplicateRules
{
    public static function apply(SourceSchema $schema, array $rows, array $existingKeys = []): void
    {
        $keyColumn = $schema->uniqueKeyColumn();
        if ($keyColumn === null) {
            return;
        }

        $firstByFingerprint = [];
        $byKey = [];
        foreach ($rows as $row) {
            assert($row instanceof RowResult);
            $fingerprint = $row->fingerprint();
            if (isset($firstByFingerprint[$fingerprint])) {
                $row->reject(Reason::exactDuplicate($firstByFingerprint[$fingerprint]));

                continue;
            }
            $firstByFingerprint[$fingerprint] = $row->rowNumber;

            $key = $row->raw[$keyColumn] ?? null;
            if ($key !== null) {
                $byKey[$key][] = $row;
            }
        }

        foreach ($byKey as $key => $group) {
            if (count($group) > 1) {
                foreach ($group as $row) {
                    $row->reject(Reason::conflictingKey($keyColumn, (string) $key));
                }

                continue;
            }
            if (isset($existingKeys[$key]) && $group[0]->isValid()) {
                $group[0]->reject(Reason::alreadyLoaded($keyColumn, (string) $key));
            }
        }
    }
}
