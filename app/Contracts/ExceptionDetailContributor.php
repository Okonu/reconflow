<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

interface ExceptionDetailContributor
{
    public const TAG = 'reconflow.exception-detail';

    public function key(): string;

    public function contribute(Model $exception, User $viewer): array;
}
