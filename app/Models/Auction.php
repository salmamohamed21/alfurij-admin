<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = [
        'listing_id', 'type', 'live_stream_time', 'start_time', 'end_time',
        'starting_price', 'current_price', 'reserve_price',
        'min_increment', 'join_fee', 'status', 'winner_id', 'participants_count'
    ];

    protected $casts = [
        'live_stream_time' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    //  Relationships
    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function participants()
    {
        return $this->hasMany(AuctionParticipant::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
