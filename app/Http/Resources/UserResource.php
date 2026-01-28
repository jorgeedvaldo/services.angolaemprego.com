<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'cv_url' => $this->cv_url,
            'subscription_start' => $this->subscription_start,
            'subscription_end' => $this->subscription_end,
            'subscription_status' => $this->subscription_status,
            'is_subscribed' => $this->hasActiveSubscription(),
            'categories' => $this->whenLoaded('categories'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
