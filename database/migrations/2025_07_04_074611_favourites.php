<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('favouritable'); // Liên kết với các bảng như hotels, locations, restaurants, dishes, ...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favourites');
    }
};