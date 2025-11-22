<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionStream extends Model
{
    protected $fillable = [
        'auction_id',
        'platform',
        'stream_url',
        'embed_url',
        'status',
        'live_start_time',
        'live_end_time',
    ];

    protected $casts = [
        'live_start_time' => 'datetime',
        'live_end_time' => 'datetime',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
}
