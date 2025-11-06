<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    protected $fillable = [
        'name',
        'description',
        'region',
        'price_range',
    ];
    public function restaurants()
{
    return $this->belongsToMany(Restaurant::class);
}

}
