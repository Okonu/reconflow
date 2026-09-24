<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\AuditActor;
use App\Contracts\SettingsSection;
use App\Support\Settings\SettingsSections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function index(Request $request, SettingsSections $sections): Response
    {
        $user = $request->user();
        $visible = array_filter($sections->all(), fn (SettingsSection $s): bool => $user !== null && Gate::forUser($user)->allows('viewSettings', $s));
        abort_if($visible === [], 403, 'You do not have access to any settings.');

        return Inertia::render('Settings', [
            'sections' => array_values(array_map(fn (SettingsSection $s): array => [
                'key' => $s->key(),
                'label' => $s->label(),
                'description' => $s->description(),
                'fields' => $s->fields(),
                'values' => $s->values(),
                'history' => $s->history(),
                'can_manage' => Gate::forUser($user)->allows('manageSettings', $s),
            ], $visible)),
        ]);
    }

    public function update(Request $request, string $section, SettingsSections $sections): RedirectResponse
    {
        $target = $sections->find($section);
        abort_if($target === null, 404);
        Gate::authorize('manageSettings', $target);
        $validated = $request->validate([...$target->rules(), 'comment' => ['required', 'string', 'min:5', 'max:500']]);
        $comment = trim((string) $validated['comment']);
        unset($validated['comment']);
        $actor = $request->user();
        abort_unless($actor instanceof AuditActor, 401);
        $target->save($validated, $actor, $comment);

        return back()->with('success', "{$target->label()} saved as a new version.");
    }
}
