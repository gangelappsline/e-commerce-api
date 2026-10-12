<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'email_verified_at' => $this->email_verified_at,
            'is_active' => $this->is_active,
            'default_role' => $this->default_role,
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()),
            'created_at' => $this->created_at,
        ];
    }
}
