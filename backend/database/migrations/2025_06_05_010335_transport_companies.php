<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy quá trình migration.
     * Tạo bảng 'transport_companies' để lưu thông tin các hãng vận tải.
     */
    public function up(): void
    {
        Schema::create('transport_companies', function (Blueprint $table) {
            // Cột 'id' tự động tăng, khóa chính của bảng.
            $table->id();
            // Liên kết khóa ngoại đến bảng 'transportations', đảm bảo mỗi hãng vận tải thuộc về một loại phương tiện cụ thể.
            // Khi loại phương tiện bị xóa, tất cả các hãng vận tải liên quan cũng sẽ bị xóa (onDelete('cascade')).
            $table->foreignId('transportation_id')->constrained()->onDelete('cascade');
            // Cột 'name' lưu tên của hãng vận tải.
            $table->string('name');
            // Cột 'description' lưu mô tả chi tiết về hãng, có thể để trống.
            $table->text('description')->nullable(); 
            // Cột 'address' lưu địa chỉ của hãng.
            $table->string('address');
            $table->decimal('latitude', 10, 7)->index();
            $table->decimal('longitude', 11, 7)->index();
            // Cột 'logo' lưu đường dẫn đến ảnh logo, có thể để trống.
            $table->string('logo')->nullable();
            // Cột 'operating_hours' lưu giờ hoạt động dưới dạng JSON, ví dụ: {"Monday": "9:00-18:00", ...}.
            $table->json('operating_hours')->nullable(); 
            // Cột 'price_range' lưu thông tin giá cước dưới dạng JSON, ví dụ: {"base_km": 12000, "additional_km": 14000, "waiting_minute_fee": 3000}.
            $table->json('price_range')->nullable();
            // Cột 'phone_number' lưu số điện thoại liên hệ, có thể để trống.
            $table->string('phone_number')->nullable();
            // Cột 'email' lưu địa chỉ email, có thể để trống.
            $table->string('email')->nullable();
            // Cột 'website' lưu đường dẫn website, có thể để trống.
            $table->string('website')->nullable();
            // Cột 'payment_methods' lưu các phương thức thanh toán hỗ trợ dưới dạng JSON array, ví dụ: ["cash", "bank_card", "momo"].
            $table->json('payment_methods')->nullable();
            // Cột 'has_mobile_app' là một boolean mặc định là false, cho biết hãng có ứng dụng di động hay không.
            $table->boolean('has_mobile_app')->default(false); 
            // Cột 'status' sử dụng enum để giới hạn trạng thái của hãng, mặc định là 'active'.
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            // Tự động tạo hai cột 'created_at' và 'updated_at' để theo dõi thời gian tạo và cập nhật bản ghi.
            $table->timestamps();
        });
    }

 
    public function down(): void
    {
        Schema::dropIfExists('transport_companies');
    }
};
