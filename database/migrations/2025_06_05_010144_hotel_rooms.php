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
        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->string('room_type', 200);
            $table->string('price_per_night');
            $table->text('description')->nullable();
            $table->string('room_area')->nullable()->comment('Room area in square meters')->nullable();
            $table->text('bed_type')->nullable()->comment('E.g., King, Queen, Twin, Double')->nullable();
            $table->unsignedTinyInteger('max_occupancy')->default(2)->comment('Maximum number of people')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_rooms');
    }
};
