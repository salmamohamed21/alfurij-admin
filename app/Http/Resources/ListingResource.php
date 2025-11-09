<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'title' => $this->title,
            'ad_type' => $this->ad_type,
            'buy_now' => (bool) $this->buy_now,
            'category' => $this->category,
            'city' => $this->city,
            'description' => $this->description,
            'price' => $this->price,
            'condition' => $this->condition,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'color' => $this->color,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
