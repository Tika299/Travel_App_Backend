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
        Schema::create('schedule_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id'); // Khóa ngoại tới bảng schedules
            $table->unsignedBigInteger('schedule_item_id'); // Khóa ngoại tới bảng schedule_items
            $table->string('type'); // Loại chi tiết (transport, accommodation, activity, note, reminder)
            $table->string('title'); // Tiêu đề chi tiết
            $table->text('content')->nullable(); // Nội dung chi tiết
            $table->string('status')->default('pending'); // Trạng thái (pending, completed, cancelled)
            $table->decimal('cost', 15, 2)->nullable(); // Chi phí
            $table->string('currency')->default('VND'); // Đơn vị tiền tệ
            $table->datetime('due_date')->nullable(); // Ngày hạn
            $table->integer('priority')->default(1); // Độ ưu tiên (1-5)
            $table->json('attachments')->nullable(); // Tệp đính kèm
            $table->json('tags')->nullable(); // Tags phân loại
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('cascade');
            $table->foreign('schedule_item_id')->references('id')->on('schedule_items')->onDelete('cascade');
            $table->index(['schedule_id', 'schedule_item_id']);
            $table->index(['type', 'status']);
            $table->index(['due_date', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_details');
    }
};
