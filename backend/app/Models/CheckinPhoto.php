<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckinPhoto extends Model
{
    protected $fillable = ['checkin_place_id', 'image'];

    public function place()
    {
        return $this->belongsTo(CheckinPlace::class, 'checkin_place_id');
    }
}
