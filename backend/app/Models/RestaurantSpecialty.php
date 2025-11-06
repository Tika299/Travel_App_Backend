<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantSpecialty extends Model
{
    protected $table = 'restaurant_specialty';

    protected $fillable = ['restaurant_id', 'specialty_id'];
    public $timestamps = true;
}
