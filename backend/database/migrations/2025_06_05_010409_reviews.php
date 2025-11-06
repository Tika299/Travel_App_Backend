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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('reviewable_id')->nullable(); // ID đối tượng được review (hotel, tour...)
            $table->string('reviewable_type')->nullable();           // Tên model được review
            $table->tinyInteger('rating')->nullable();               // điểm đánh giá 1-5
            $table->text('content');                     // nội dung bài viết
            $table->boolean('is_approved')->default(false); // trạng thái duyệt bài viết
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
