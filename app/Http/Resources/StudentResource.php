<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'group_id' => $this->group_id,
            'record_book_number' => $this->record_book_number,
            'status' => $this->status,
            'last_name' => $this->whenLoaded('user', fn() => $this->user->last_name),
            'first_name' => $this->whenLoaded('user', fn() => $this->user->first_name),
            'middle_name' => $this->whenLoaded('user', fn() => $this->user->middle_name),
            'full_name' => $this->whenLoaded('user', fn() => trim(
                "{$this->user->last_name} {$this->user->first_name} {$this->user->middle_name}"
            )),
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'current_course' => $this->group->current_course,
                'faculty_id' => $this->group->faculty_id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
