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
        Schema::create('cuisine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categories_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('image')->nullable();
            $table->text('short_description');
            $table->text('detailed_description') ->nullable();
            $table->enum('region', ['Miền Bắc', 'Miền Trung', 'Miền Nam']) ->nullable();
            $table->integer('price');
            $table->text('address');
            $table->string('serving_time')->nullable();
            $table->boolean('delivery')->default(false);
            $table->string('operating_hours')->nullable();
            $table->string('suitable_for')->nullable();
            $table->enum('status', ['available', 'unavailable'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuisine');
    }
};
