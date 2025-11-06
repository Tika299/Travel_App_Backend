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
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('hotel_id')->nullable()->after('checkin_place_id');
            $table->unsignedBigInteger('restaurant_id')->nullable()->after('hotel_id');
            
            // Thêm foreign key constraints
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('set null');
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['hotel_id']);
            $table->dropForeign(['restaurant_id']);
            
            $table->dropColumn(['hotel_id', 'restaurant_id']);
        });
    }
};
