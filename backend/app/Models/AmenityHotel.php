<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmenityHotel extends Model
{
    protected $table = 'amenity_hotels'; // Rất quan trọng!
    


    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

}
