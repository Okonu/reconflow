<?php

declare(strict_types=1);

namespace Modules\AI\Console\Commands;

use Illuminate\Console\Command;
use Modules\AI\Contracts\LlmClient;
use Modules\AI\Services\EvalRunner;
use Modules\AI\Services\StubLlmClient;

final class RunEvalCommand extends Command
{
    protected $signature = 'reconflow:ai-eval {--set=triage_v1 : Eval set in Modules/AI/resources/evals} {--stub : Use the offline rule-based stub instead of the configured model}';

    protected $description = 'Score the triage prompt against a labelled eval set';

    public function handle(EvalRunner $runner, LlmClient $client): int
    {
        $client = $this->option('stub') ? new StubLlmClient : $client;
        if (! $client->available()) {
            $this->error('The configured AI client is unavailable (no API key). Use --stub for an offline baseline.');

            return self::FAILURE;
        }
        $run = $runner->run($client, (string) $this->option('set'));
        $this->table(['Model', 'Prompt', 'Cases', 'Action correct', 'Cause correct', 'Errors'], [[
            $run->model, $run->prompt_version, $run->cases, $run->action_correct, $run->cause_correct, $run->errors,
        ]]);

        return self::SUCCESS;
    }
}
