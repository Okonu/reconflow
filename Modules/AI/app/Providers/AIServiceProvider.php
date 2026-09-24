<?php

declare(strict_types=1);

namespace Modules\AI\Providers;

use App\Contracts\ExceptionDetailContributor;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Modules\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\AI\Console\Commands\PruneAiLogsCommand;
use Modules\AI\Console\Commands\RunEvalCommand;
use Modules\AI\Contracts\LlmClient;
use Modules\AI\Enums\AiPermission;
use Modules\AI\Models\AiSuggestion;
use Modules\AI\Policies\AiSuggestionPolicy;
use Modules\AI\Services\ClaudeClient;
use Modules\AI\Services\StubLlmClient;
use Modules\AI\Support\AiContribution;
use Modules\AI\Support\Demo\AiTables;
use Modules\Ingestion\Actions\ResetDemoData;

final class AIServiceProvider extends ModuleProvider
{
    protected string $name = 'AI';

    protected string $nameLower = 'ai';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        RunEvalCommand::class,
        PruneAiLogsCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(LlmClient::class, fn ($app) => config('ai.driver') === 'stub'
            ? $app->make(StubLlmClient::class)
            : $app->make(ClaudeClient::class));
        $this->app->tag([AiContribution::class], ExceptionDetailContributor::TAG);
        $this->app->tag([AiTables::class], ResetDemoData::RESETTER_TAG);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(AiPermission::class);
        Gate::policy(AiSuggestion::class, AiSuggestionPolicy::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(PruneAiLogsCommand::class)->dailyAt('03:30')->onOneServer();
    }
}
