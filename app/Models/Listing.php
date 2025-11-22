<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    protected $fillable = [
        'seller_id', 'ad_type', 'buy_now', 'title', 'category', 'section', 'city',
        'description', 'price', 'status', 'condition', 'model', 'serial_number',
        'cabin_type', 'vehicle_type', 'engine_capacity', 'transmission', 'fuel_type',
        'lights_type', 'color', 'length', 'width', 'height', 'kilometers', 'registration_year',
        'gearbox_brand', 'gearbox_type',
        'location', 'media', 'documents', 'files', 'other',
        'approval_status', 'approved_by', 'approved_at'
    ];

    protected $casts = [
        'buy_now' => 'boolean',
        'price' => 'decimal:2',
        'kilometers' => 'decimal:2',
        'registration_year' => 'integer',
        'location' => 'array',
        'media' => 'array',
        'documents' => 'array',
        'files' => 'array',
        'other' => 'array',
        'approved_at' => 'datetime',
    ];

    //  Relationships
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function auction()
    {
        return $this->hasOne(Auction::class);
    }
}
