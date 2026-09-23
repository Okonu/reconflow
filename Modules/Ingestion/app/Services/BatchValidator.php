<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Modules\Ingestion\DTOs\ValidationOutcome;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Support\DuplicateRules;
use Modules\Ingestion\Support\Schema\ValidationContext;

final class BatchValidator
{
    public function validate(SourceType $source, array $rows, ValidationContext $context, array $existingKeys = []): ValidationOutcome
    {
        $schema = $source->schema();
        $results = [];
        foreach ($rows as $rowNumber => $raw) {
            $results[] = $schema->validate((int) $rowNumber, $raw, $context);
        }
        DuplicateRules::apply($schema, $results, $existingKeys);

        return new ValidationOutcome($results);
    }
}
