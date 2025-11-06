<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import BelongsTo for clarity
use App\Models\Hotel;

class CheckInPlace extends Model
{
    use HasFactory;

    protected $table = 'checkin_places';

    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'image',
        'location_id',
        'price',
        'is_free',
        'operating_hours',
        'images',
        'region',
        'caption',
        'transport_options',
        'status',
        // Uncomment the line below if you plan to assign a hotel_id directly
        // 'hotel_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_free' => 'boolean',
        'operating_hours' => 'array',
        'images' => 'array',
        'transport_options' => 'array',
    ];

    /**
     * Get the location that owns the CheckinPlace.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class); // Assuming you have a Location model
    }

    /**
     * Get the hotel that this check-in place belongs to.
     */
    public function hotel(): BelongsTo
    {
        // Assumes a 'hotel_id' foreign key on the 'checkin_places' table
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    /**
     * Get all of the reviews for the CheckinPlace.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    // You can add other relationships here as needed, e.g.:
    // public function visitedUsers()
    // {
    //     return $this->belongsToMany(User::class, 'checkin_place_user');
    // }
}