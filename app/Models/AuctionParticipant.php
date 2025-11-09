<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionParticipant extends Model
{
    protected $fillable = [
        'auction_id', 'user_id', 'join_fee', 'total_bids', 'total_spent', 'is_winner'
    ];

    protected $casts = [
        'is_winner' => 'boolean',
        'join_fee' => 'decimal:2',
        'total_spent' => 'decimal:2',
    ];

    //  Relationships
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
