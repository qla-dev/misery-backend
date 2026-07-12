<?php
namespace App\Http\Resources; use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class CardResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'title'=>$this->title,'subtitle'=>$this->subtitle,'score'=>$this->score,'image'=>$this->image,'deck'=>$this->deck]; } }
