<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'target_user_id' => $this->target_user_id,
            'payload' => $this->payload ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
