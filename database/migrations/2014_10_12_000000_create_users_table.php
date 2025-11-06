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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->index();
            $table->string('password');
            $table->string('phone')->nullable(); // Thêm số điện thoại
            $table->string('status')->default('active'); // Thêm trạng thái
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->timestamp('email_verified_at')->nullable()->index();
            $table->string('role')->default('user'); // Phân quyền
            $table->rememberToken(); // Token ghi nhớ đăng nhập
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Schema::dropIfExists('users');
        Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['phone', 'status']);
    });
    }
};

