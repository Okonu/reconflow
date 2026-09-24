<?php

declare(strict_types=1);

use Modules\AI\Contracts\LlmClient;
use Modules\AI\DTOs\LlmRequest;
use Modules\AI\DTOs\LlmResult;

final class CapturingLlmClient implements LlmClient
{
    public array $requests = [];

    public function __construct(public array $output = [], public bool $available = true) {}

    public function name(): string
    {
        return 'capturing-test-model';
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function complete(LlmRequest $request): LlmResult
    {
        $this->requests[] = ['system' => $request->system, 'user' => $request->userContent()];

        return new LlmResult($this->output ?: [
            'likely_cause' => 'customer_underpaid',
            'recommended_action' => 'write_off',
            'explanation' => 'The customer paid less than expected.',
            'evidence' => ['received below expected'],
            'confidence' => 0.8,
        ], $this->name(), 120, 40);
    }
}

function fakeLlm(array $output = []): CapturingLlmClient
{
    $fake = new CapturingLlmClient($output);
    app()->instance(LlmClient::class, $fake);

    return $fake;
}
