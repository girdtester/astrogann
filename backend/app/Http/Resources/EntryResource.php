<?php

namespace App\Http\Resources;

use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Entry */
class EntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'image_url' => $this->imageUrl(),
            'entry_date' => $this->entry_date->toDateString(),
            'entry_time' => $this->timeLabel(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Ready-to-show strings (D-78) so web and app read the same: "06 Oct 2026", "3:45 PM".
            'display' => [
                'date' => Fmt::date($this->entry_date),
                'time' => Fmt::time($this->entry_time),
                'added_on' => Fmt::dateTime($this->created_at),
                'added_ago' => Fmt::ago($this->created_at),
            ],
        ];
    }
}
