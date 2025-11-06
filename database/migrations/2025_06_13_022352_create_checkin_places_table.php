<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Hàm up() được gọi khi chạy lệnh migrate
    public function up(): void
    {
        // Tạo bảng checkin_places
        Schema::create('checkin_places', function (Blueprint $table) {
            $table->id(); // Tạo khóa chính tự tăng (id)
            $table->string('name'); // Tên địa điểm check-in
            $table->text('description')->nullable(); // Mô tả chi tiết (có thể để trống)
            $table->string('address')->nullable(); // Địa chỉ (có thể để trống)
            $table->decimal('latitude', 10, 7)->nullable(); // Vĩ độ (có thể để trống)
            $table->decimal('longitude', 10, 7)->nullable(); // Kinh độ (có thể để trống)
            $table->string('image')->nullable(); // Ảnh đại diện (có thể để trống)
            $table->unsignedBigInteger('location_id')->nullable(); // ID vị trí liên kết (có thể để trống)
            $table->decimal('price', 15, 2)->nullable(); // Giá vé (có thể để trống)
            $table->boolean('is_free')->default(false); // Có miễn phí không, mặc định là không
            $table->json('operating_hours')->nullable(); // Giờ hoạt động (định dạng JSON, có thể để trống)
            $table->json('images')->nullable(); // Danh sách ảnh (JSON, có thể để trống)
            $table->string('region')->nullable(); // Miền (Bắc, Trung, Nam...) (có thể để trống)
            $table->text('caption')->nullable(); // Chú thích ngắn (có thể để trống)
            $table->json('transport_options')->nullable(); // Các phương tiện đến được (JSON, có thể để trống)
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active'); // Trạng thái của địa điểm

            $table->timestamps(); // Tạo 2 cột created_at và updated_at

            // Thiết lập khóa ngoại tới bảng locations, nếu bị xóa thì đặt về null
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('set null');
        });
    }

    // Hàm down() được gọi khi rollback
    public function down(): void
    {
        Schema::dropIfExists('checkin_places'); // Xóa bảng nếu tồn tại
    }
};
