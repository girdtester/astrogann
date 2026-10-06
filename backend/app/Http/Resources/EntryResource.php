<?php

namespace App\Http\Resources;

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
        ];
    }
}
