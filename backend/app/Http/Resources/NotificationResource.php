<?php

namespace App\Http\Resources;

use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value, // new_entry | expiry | subscription | announcement
            'title' => $this->title(),     // D-100: announcement title, category name or "Subscription"
            'message' => $this->message,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
            'display' => ['date_time' => Fmt::dateTime($this->created_at), 'ago' => Fmt::ago($this->created_at)],
        ];
    }
}
