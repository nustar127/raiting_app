<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'login' => $this->login,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'full_name' => trim("{$this->last_name} {$this->first_name} {$this->middle_name}"),
            'role' => [
                'code' => $this->role?->code,
                'name' => $this->role?->name,
            ],
            'is_blocked' => $this->is_blocked,
            'must_change_password' => $this->must_change_password,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
