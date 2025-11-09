<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $fillable = [
        'auction_id', 'bidder_id', 'amount', 'is_auto_bid'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_auto_bid' => 'boolean',
    ];

    // 🔗 Relationships
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function bidder()
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }
}
