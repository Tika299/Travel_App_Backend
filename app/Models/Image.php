<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'image_path',
        'is_cover',
    ];

    public function imageable()
    {
        return $this->morphTo();
    }
}
