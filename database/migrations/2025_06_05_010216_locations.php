<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Đây là bảng 'locations', chỉ dùng để lưu trữ thông tin về các THÀNH PHỐ hoặc KHU VỰC LỚN.
     * Nó sẽ không chứa các chi tiết của một điểm check-in cụ thể (như giờ mở cửa, giá vé, v.v.).
     * Các điểm check-in cụ thể sẽ được lưu trong bảng 'checkin_places' và liên kết tới đây.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id(); // ID duy nhất cho mỗi thành phố/khu vực

            // Tên của thành phố/khu vực. Nên là duy nhất.
            $table->string('name')->unique(); 

            // Mô tả tổng quan về thành phố/khu vực (ví dụ: "Thành phố thủ đô của Việt Nam...")
            $table->text('description')->nullable();

            // Ảnh đại diện cho thành phố/khu vực
            $table->string('image')->nullable(); 

            // Tọa độ trung tâm của thành phố/khu vực (có thể để null nếu không cần quá chính xác)
            $table->decimal('latitude', 10, 7)->nullable()->index();
            $table->decimal('longitude', 11, 7)->nullable()->index();

            // Nếu bạn muốn có một đường dẫn URL thân thiện cho thành phố (ví dụ: /locations/ha-noi)
            // $table->string('slug')->unique()->nullable(); 

            // Các cột thời gian tạo và cập nhật
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};

