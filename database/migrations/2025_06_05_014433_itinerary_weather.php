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
Schema::create('itinerary_weather', function (Blueprint $table) {
    $table->id();
    $table->foreignId('itinerary_id')->constrained()->onDelete('cascade');
    $table->foreignId('weather_data_id')->constrained('weather_data')->onDelete('cascade');
    $table->timestamps();
});

}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_weather');
    }
};
