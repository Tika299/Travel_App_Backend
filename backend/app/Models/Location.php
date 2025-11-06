<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import HasMany cho quan hệ


class Location extends Model
{
    use HasFactory;

    /**
     * Tên bảng mà model liên kết đến.
     * Mặc định Laravel sẽ dùng dạng số nhiều của tên model (locations).
     * Tuy nhiên, việc khai báo rõ ràng là tốt.
     *
     * @var string
     */
    protected $table = 'locations';

    /**
     * Các thuộc tính có thể được gán hàng loạt (mass assignable).
     * Đây là các cột bạn cho phép gán giá trị khi tạo hoặc cập nhật bản ghi
     * bằng cách dùng create() hoặc fill().
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'image',
        'latitude',
        'longitude',
        // 'slug', // Nếu bạn quyết định thêm cột slug vào bảng locations
    ];

    /**
     * Các thuộc tính nên được cast sang kiểu dữ liệu cụ thể.
     * Ví dụ: latitude và longitude có thể muốn cast sang float.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Định nghĩa mối quan hệ: Một Location (thành phố) có nhiều CheckinPlace.
     * Model CheckinPlace sẽ có khóa ngoại 'location_id' trỏ về bảng này.
     *
     * @return HasMany
     */
    public function checkinPlaces(): HasMany
    {
        return $this->hasMany(CheckinPlace::class);
    }

   
}
