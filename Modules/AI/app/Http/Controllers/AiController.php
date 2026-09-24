<?php

declare(strict_types=1);

namespace Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AI\Actions\DecideSuggestion;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Http\Requests\DecideSuggestionRequest;
use Modules\AI\Http\Resources\AiSuggestionResource;
use Modules\AI\Models\AiSuggestion;
use Modules\AI\Services\AiSettings;
use Modules\AI\Services\AiUnavailable;
use Modules\AI\Services\NarrativeService;
use Modules\AI\Services\OversightStats;
use Modules\AI\Services\TriageService;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Users\Models\User;

final class AiController extends Controller
{
    public function triage(Request $request, ReconException $exception, TriageService $triage): RedirectResponse
    {
        $this->authorize('create', AiSuggestion::class);
        $this->authorize('view', $exception);
        try {
            $triage->suggest($this->user($request), $exception);
        } catch (AiUnavailable $e) {
            return back()->with('error', "AI suggestion unavailable: {$e->getMessage()}");
        }

        return back()->with('success', 'AI suggestion ready. Review it before acting: it does not change anything by itself.');
    }

    public function decide(DecideSuggestionRequest $request, AiSuggestion $suggestion, DecideSuggestion $decide): RedirectResponse
    {
        $request->overriding()
            ? $decide->override($this->user($request), $suggestion, $request->action(), $request->reason())
            : $decide->accept($this->user($request), $suggestion);

        return back()->with('success', $request->overriding() ? 'Override recorded.' : 'Suggestion accepted.');
    }

    public function latestSummary(Request $request, ReconRun $run): JsonResponse
    {
        $this->authorize('view', $run);
        $latest = AiSuggestion::query()->where('kind', SuggestionKind::RunSummary->value)->where('run_id', $run->id)
            ->whereNot('status', 'failed')->latest('id')->first();

        return response()->json([
            'summary' => $latest === null ? null : (new AiSuggestionResource($latest))->resolve($request),
            'status' => app(AiSettings::class)->status(),
            'can_request' => $request->user()?->can('create', AiSuggestion::class) ?? false,
        ]);
    }

    public function summarise(Request $request, ReconRun $run, NarrativeService $narrative): JsonResponse
    {
        $this->authorize('create', AiSuggestion::class);
        $this->authorize('view', $run);
        try {
            $summary = $narrative->summarise($this->user($request), $run);
        } catch (AiUnavailable $e) {
            return response()->json(['error' => ['code' => 'ai_unavailable', 'message' => $e->getMessage()]], 503);
        }

        return response()->json(['summary' => (new AiSuggestionResource($summary))->resolve($request)], 201);
    }

    public function oversight(Request $request, OversightStats $stats, AiSettings $settings): Response
    {
        $this->authorize('oversee', AiSuggestion::class);

        return Inertia::render('AI/Oversight/Index', [
            'status' => $settings->status(),
            'stats' => $stats->summary(),
            'overrides' => AiSuggestionResource::collection($stats->recentOverrides()),
            'recent' => AiSuggestionResource::collection($stats->recent()),
            'evals' => $stats->evals(),
            'prompt_version' => config('ai.prompt_version'),
            'retention_months' => (int) config('ai.log_retention_months'),
            'can' => ['manage' => $request->user()?->can('manage', AiSuggestion::class) ?? false],
        ]);
    }

    public function toggleKillSwitch(Request $request, AiSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', AiSuggestion::class);
        $user = $this->user($request);
        $on = ! $settings->killed();
        $settings->setKilled($on, $user->id, $user->name);
        $audit->record(AiAuditAction::KillSwitchToggled, $user, 'ai_settings', null, ['kill_switch' => $on]);

        return back()->with('success', $on ? 'AI assistant switched off for everyone.' : 'AI assistant switched back on.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
