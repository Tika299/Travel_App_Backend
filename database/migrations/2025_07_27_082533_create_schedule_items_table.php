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
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id'); // Khóa ngoại tới bảng schedules
            $table->string('title'); // Tiêu đề sự kiện
            $table->datetime('start_time'); // Thời gian bắt đầu
            $table->datetime('end_time')->nullable(); // Thời gian kết thúc
            $table->text('location')->nullable(); // Địa điểm
            $table->text('description')->nullable(); // Mô tả
            $table->string('cost')->nullable(); // Chi phí
            $table->string('weather')->nullable(); // Thông tin thời tiết
            $table->boolean('all_day')->default(false); // Sự kiện cả ngày
            $table->string('repeat')->default('none'); // Lặp lại (none, daily, weekly, monthly, yearly)
            $table->integer('order')->default(0); // Thứ tự hiển thị
            $table->json('custom_fields')->nullable(); // Các trường tùy chỉnh
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('cascade');
            $table->index(['schedule_id', 'start_time']);
            $table->index(['schedule_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};
