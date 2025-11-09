<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'starting_price' => $this->starting_price,
            'current_price' => $this->current_price,
            'reserve_price' => $this->reserve_price,
            'min_increment' => $this->min_increment,
            'join_fee' => $this->join_fee,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'live_stream_time' => $this->live_stream_time,
            'participants_count' => $this->participants_count,
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'winner' => $this->whenLoaded('winner'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
