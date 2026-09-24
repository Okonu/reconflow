<?php

declare(strict_types=1);

namespace Modules\AI\Contracts;

use Modules\AI\DTOs\LlmRequest;
use Modules\AI\DTOs\LlmResult;

interface LlmClient
{
    public function name(): string;

    public function available(): bool;

    public function complete(LlmRequest $request): LlmResult;
}
