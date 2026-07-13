<?php
namespace App\Http\Resources; use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class UserResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'name'=>$this->name,'email'=>$this->email,'color'=>$this->color,'pro_status'=>$this->pro_status,'pro_started_at'=>$this->pro_started_at,'pro_ends_at'=>$this->pro_ends_at,'revenuecat_product_id'=>$this->revenuecat_product_id,'revenuecat_entitlement_id'=>$this->revenuecat_entitlement_id]; } }
