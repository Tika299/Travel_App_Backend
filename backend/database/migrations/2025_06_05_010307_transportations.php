<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transportations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Tên loại xe (Ô tô, Xe máy...)
            $table->string('icon');                          // Icon nhỏ (ví dụ: car.svg)
            $table->string('banner')->nullable();            // Ảnh banner lớn
            $table->decimal('average_price', 10, 2)->nullable(); // Giá trung bình (nếu có)
            $table->text('description')->nullable();         // Mô tả dài về loại xe
            $table->json('tags')->nullable();                // ["uy_tin", "pho_bien", "cong_nghe"]
            $table->json('features')->nullable();            // ["has_app", "card_payment", "insurance"]
            $table->boolean('is_visible')->default(true);     // Hiển thị hay không
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transportations');
    }
};

