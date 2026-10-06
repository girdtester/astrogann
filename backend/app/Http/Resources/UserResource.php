<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
                'kind' => $this->role->kind->value, // superadmin | staff | user
            ],
            'permissions' => $this->when(! $this->isTrader(), fn () => $this->permissionKeys()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
