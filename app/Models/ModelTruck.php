<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelTruck extends Model
{
    protected $fillable = [
        'truck_name',
        'model_name',
        'image_path',
    ];
}
