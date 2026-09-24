<?php

declare(strict_types=1);

namespace Modules\AI\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\AI\Enums\AiPermission;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiSuggestion;

final class AiSuggestionPolicy
{
    use AuthorizesPermissions;

    public function create(User $user): Response
    {
        return $this->permit($user, AiPermission::Use);
    }

    public function decide(User $user, AiSuggestion $suggestion): Response
    {
        $allowed = $this->permit($user, AiPermission::Use);
        if ($allowed->denied()) {
            return $allowed;
        }
        if ($suggestion->kind !== SuggestionKind::Triage || $suggestion->status !== SuggestionStatus::Pending) {
            return Response::deny('Only pending triage suggestions can be accepted or overridden.');
        }

        return Response::allow();
    }

    public function oversee(User $user): Response
    {
        return $this->permit($user, AiPermission::Oversee);
    }

    public function manage(User $user): Response
    {
        return $this->permit($user, AiPermission::Manage);
    }
}
