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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên lịch trình
            $table->date('start_date'); // Ngày bắt đầu
            $table->date('end_date'); // Ngày kết thúc
            $table->unsignedBigInteger('checkin_place_id'); // Khóa ngoại tới bảng checkin_places
            $table->integer('participants'); // Số người tham gia
            $table->text('description')->nullable(); // Mô tả
            $table->decimal('budget', 15, 2)->nullable(); // Ngân sách
            $table->enum('status', ['upcoming', 'completed', 'planning'])->default('planning'); // Trạng thái
            $table->unsignedTinyInteger('progress')->default(0); // Tiến độ theo phần trăm
            $table->unsignedBigInteger('user_id'); // Khóa ngoại người dùng
            $table->timestamps();

            $table->foreign('checkin_place_id')->references('id')->on('checkin_places')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
