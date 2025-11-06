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
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('likeable_id'); // ID đối tượng được thích (hotel, tour...)
            $table->string('likeable_type');           // Tên model được thích
            $table->timestamps();

            $table->unique(['user_id', 'likeable_id', 'likeable_type'], 'unique_like'); // Đảm bảo mỗi người dùng chỉ thích một đối tượng một lần
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('likes');
    }
};
