<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('itinerary_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->string('title'); // Tên event con (ví dụ: "Đi Hồ Gươm")
            $table->text('description')->nullable(); // Mô tả chi tiết
            $table->string('type')->default('activity'); // Loại: activity, restaurant, hotel, transport
            $table->date('date'); // Ngày thực hiện
            $table->time('start_time')->nullable(); // Giờ bắt đầu
            $table->time('end_time')->nullable(); // Giờ kết thúc
            $table->integer('duration')->nullable(); // Thời gian (phút)
            $table->decimal('cost', 12, 2)->default(0); // Chi phí
            $table->string('location')->nullable(); // Địa điểm cụ thể
            $table->json('metadata')->nullable(); // Dữ liệu bổ sung (rating, reviews, etc.)
            $table->integer('order_index')->default(0); // Thứ tự trong ngày
            $table->timestamps();
            
            $table->index(['schedule_id', 'date', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_events');
    }
};


