<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Notifications\Http\Resources\NotificationResource;
use Modules\Users\Models\User;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => NotificationResource::collection($user->notifications()->limit((int) config('notifications.inbox_size'))->get())->resolve($request),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->user($request)->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['unread' => $this->user($request)->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
