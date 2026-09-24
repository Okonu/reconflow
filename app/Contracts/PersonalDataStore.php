<?php

declare(strict_types=1);

namespace App\Contracts;

interface PersonalDataStore
{
    public const TAG = 'reconflow.personal-data-stores';

    public function anonymiseBefore(string $date, string $replacement): array;

    public function anonymiseSubject(array $phoneVariants, string $replacement): array;
}
