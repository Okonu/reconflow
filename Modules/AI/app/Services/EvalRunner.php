<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Contracts\LlmClient;
use Modules\AI\DTOs\LlmRequest;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Models\AiEvalRun;
use Modules\AI\Support\Schemas;
use Modules\Audit\Services\AuditLogger;
use RuntimeException;
use Throwable;

final class EvalRunner
{
    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly Redactor $redactor,
        private readonly AuditLogger $audit,
    ) {}

    public function run(LlmClient $client, string $set = 'triage_v1', ?int $userId = null): AiEvalRun
    {
        $path = module_path('AI', "resources/evals/{$set}.json");
        $cases = json_decode((string) @file_get_contents($path), true);
        if (! is_array($cases)) {
            throw new RuntimeException("Eval set not found or invalid: {$set}");
        }
        $prompt = $this->prompts->load(SuggestionKind::Triage);
        $results = [];
        $actionCorrect = $causeCorrect = $errors = 0;

        foreach ($cases as $case) {
            try {
                $output = $client->complete(new LlmRequest($prompt['text'], $this->redactor->redact($case['context']), Schemas::triage()))->output;
                if (! Schemas::validTriage($output)) {
                    throw new RuntimeException('Output did not match the schema.');
                }
                $actionOk = in_array($output['recommended_action'], $case['expected']['recommended_action'], true);
                $causeOk = in_array($output['likely_cause'], $case['expected']['likely_cause'], true);
                $actionCorrect += (int) $actionOk;
                $causeCorrect += (int) $causeOk;
                $results[] = ['id' => $case['id'], 'action' => $output['recommended_action'], 'cause' => $output['likely_cause'], 'action_ok' => $actionOk, 'cause_ok' => $causeOk];
            } catch (Throwable $e) {
                $errors++;
                $results[] = ['id' => $case['id'], 'error' => mb_substr($e->getMessage(), 0, 300)];
            }
        }

        $run = AiEvalRun::query()->create([
            'eval_set' => $set,
            'model' => $client->name(),
            'prompt_version' => $prompt['version'],
            'prompt_hash' => $prompt['hash'],
            'cases' => count($cases),
            'action_correct' => $actionCorrect,
            'cause_correct' => $causeCorrect,
            'errors' => $errors,
            'results' => $results,
            'triggered_by' => $userId,
        ]);
        $this->audit->record(AiAuditAction::EvalRun, null, 'ai_eval_run', $run->id, [
            'eval_set' => $set, 'model' => $run->model, 'cases' => $run->cases, 'action_correct' => $actionCorrect, 'cause_correct' => $causeCorrect, 'errors' => $errors,
        ]);

        return $run;
    }
}
