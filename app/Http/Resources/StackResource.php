<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'icon_key' => $this->icon_key,
            'description' => $this->description,
            'description_bs' => $this->description_bs,
            'is_premium' => $this->slug !== 'normal',
            'active_cards_count' => (int) ($this->active_cards_count ?? 0),
        ];
    }
}
