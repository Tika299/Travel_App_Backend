<?php
 
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class UsersTableSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'phone' => '0123456789',
            'status' => 'active',
            'role' => 'admin',
            'bio' => 'Quản trị hệ thống',
            'avatar' => 'img/68830be304d8c.jpg',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        User::create([
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyenvana@example.com',
            'password' => Hash::make('password'),
            'phone' => '0987654321',
            'status' => 'active',
            'role' => 'user',
            'bio' => 'Yêu thích du lịch',
            'avatar' => 'img/avatar_user_review.jpg',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        User::create([
            'name' => 'Trần Thị B',
            'email' => 'tranthib@example.com',
            'password' => Hash::make('password'),
            'phone' => '0911222333',
            'status' => 'active',
            'role' => 'user',
            'bio' => 'Đam mê ẩm thực',
            'avatar' => 'img/avatar_user_review.jpg',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        User::create([
            'name' => 'Lê Văn C',
            'email' => 'levanc@example.com',
            'password' => Hash::make('password'),
            'phone' => '0933444555',
            'status' => 'active',
            'role' => 'user',
            'bio' => 'Reviewer khách sạn',
            'avatar' => 'img/avatar_user_review.jpg',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        User::create([
            'name' => 'Phạm Thị D',
            'email' => 'phamthid@example.com',
            'password' => Hash::make('password'),
            'phone' => '0966778899',
            'status' => 'active',
            'role' => 'user',
            'bio' => 'Hướng dẫn viên địa phương',
            'avatar' => 'img/avatar_user_review.jpg',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);

        User::create([
            'name' => 'Admin',
            'email' => 'admin123@gmail.com',
            'password' => Hash::make('password'),
            'phone' => '0321645879',
            'status' => 'active',
            'role' => 'admin',
            'bio' => 'Quản trị hệ thống',
            'avatar' => 'img/68830be304d8c.jpg',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);
    }
}
