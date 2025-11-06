<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TransportCompany extends Model
{
    use HasFactory;

    protected $table = 'transport_companies';

    // Chỉ định các cột có thể gán giá trị hàng loạt.
    // Các cột này khớp với migration ban đầu.
    protected $fillable = [
        'transportation_id',
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'logo',
        'operating_hours',
        'price_range',
        'phone_number',
        'email',
        'website',
        'payment_methods',
        'has_mobile_app',
        'status',
    ];

    // Khai báo các cột JSON và boolean để Laravel tự động cast.
    protected $casts = [
        'operating_hours' => 'array',
        'price_range' => 'array',
        'payment_methods' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'has_mobile_app' => 'boolean',
    ];

    /**
     * Quan hệ: hãng xe thuộc một loại phương tiện.
     */
    public function transportation()
    {
        return $this->belongsTo(Transportation::class);
    }

  public function reviews(): MorphMany // Đảm bảo type hint là MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }}