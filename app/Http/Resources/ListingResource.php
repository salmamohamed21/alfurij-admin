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
            'seller' => $this->whenLoaded('seller', function () {
                return [
                    'name' => $this->seller->name,
                    'phone' => $this->seller->phone,
                ];
            }),
            'title' => $this->title,
            'ad_type' => $this->ad_type,
            'buy_now' => (bool) $this->buy_now,
            'category' => $this->category,
            'city' => $this->city,
            'description' => $this->description,
            'price_in_sar' => $this->price,
            'price_in_points' => sar_to_points($this->price),
            'condition' => $this->condition,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'cabin_type' => $this->cabin_type,
            'vehicle_type' => $this->vehicle_type,
            'engine_capacity' => $this->engine_capacity,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'lights_type' => $this->lights_type,
            'color' => $this->color,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'kilometers' => $this->kilometers,
            'registration_year' => $this->registration_year,
            'gearbox_brand' => $this->gearbox_brand,
            'gearbox_type' => $this->gearbox_type,
            'other' => $this->other,
            'section' => $this->section,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'media' => array_map(function ($path) {
                return str_replace('\\', '/', $path);
            }, $this->media ?? []),
            'documents' => array_map(function ($path) {
                return str_replace('\\', '/', $path);
            }, $this->documents ?? []),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
