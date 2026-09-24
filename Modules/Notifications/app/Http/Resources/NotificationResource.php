<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $notification = $this->resource;
        assert($notification instanceof DatabaseNotification);
        $data = (array) $notification->data;

        return [
            'id' => $notification->id,
            'kind' => $data['kind'] ?? 'alert',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'lines' => $data['lines'] ?? [],
            'url' => $data['url'] ?? null,
            'level' => $data['level'] ?? 'info',
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
