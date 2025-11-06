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
        Schema::table('itinerary_events', function (Blueprint $table) {
            // Thêm foreign keys để liên kết với dữ liệu thật từ database
            $table->unsignedBigInteger('checkin_place_id')->nullable()->after('schedule_id');
            $table->unsignedBigInteger('hotel_id')->nullable()->after('checkin_place_id');
            $table->unsignedBigInteger('restaurant_id')->nullable()->after('hotel_id');
            
            // Thêm foreign key constraints
            $table->foreign('checkin_place_id')->references('id')->on('checkin_places')->onDelete('set null');
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('set null');
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itinerary_events', function (Blueprint $table) {
            // Xóa foreign key constraints trước
            $table->dropForeign(['checkin_place_id']);
            $table->dropForeign(['hotel_id']);
            $table->dropForeign(['restaurant_id']);
            
            // Xóa các cột
            $table->dropColumn(['checkin_place_id', 'hotel_id', 'restaurant_id']);
        });
    }
};
