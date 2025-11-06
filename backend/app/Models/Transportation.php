<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transportation extends Model
{
    use HasFactory;
    // Nếu bạn đang dùng SoftDeletes, bỏ comment dòng dưới:
    // use SoftDeletes;

    protected $fillable = [
        'name',
        'icon',
        'banner',
        'average_price',
        'description',
        'tags',
        'features',
        'is_visible',
    ];

    /**
     * Các thuộc tính nên được ép kiểu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'features' => 'array',
        'is_visible' => 'boolean',
    ];

    /**
     * Một loại phương tiện có nhiều hãng.
     */
    public function companies()
    {
        return $this->hasMany(TransportCompany::class);
    }
}
