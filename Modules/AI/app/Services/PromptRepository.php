<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Enums\SuggestionKind;
use RuntimeException;

final class PromptRepository
{
    public function version(): string
    {
        return (string) config('ai.prompt_version');
    }

    public function load(SuggestionKind $kind): array
    {
        $path = module_path('AI', "resources/prompts/{$this->version()}_{$kind->prompt()}.md");
        if (! is_file($path)) {
            throw new RuntimeException("Prompt not found: {$path}");
        }
        $text = (string) file_get_contents($path);

        return ['text' => $text, 'version' => $this->version(), 'hash' => hash('sha256', $text)];
    }
}
