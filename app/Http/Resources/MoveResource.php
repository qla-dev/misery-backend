<?php
namespace App\Http\Resources; use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class MoveResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'game_id'=>$this->game_id,'player_id'=>$this->player_id,'player'=>new UserResource($this->whenLoaded('player')),'card'=>new CardResource($this->whenLoaded('card')),'correct'=>$this->correct,'is_steal'=>$this->is_steal,'created_at'=>$this->created_at]; } }
