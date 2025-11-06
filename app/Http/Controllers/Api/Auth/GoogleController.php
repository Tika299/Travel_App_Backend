<?php

namespace App\Http\Controllers\Api\Auth;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

use App\Models\User;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    //
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        // Tìm hoặc tạo người dùng
        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password' => bcrypt(Str::random(16)),
                'bio' => 'abc', // 👈 Thêm dòng này để tránh lỗi nếu không có bio từ Google
                'phone' => '0999999999', // 👈 thêm dòng này
            ]
        );

        // Tạo token và trả về frontend
        $token = $user->createToken('auth_token')->plainTextToken;

        // return redirect("http://localhost:5173/google-success?token=$token");

        // return redirect("http://localhost:5173/");
        return redirect("http://localhost:5173/oauth-success?token=$token&email=" . urlencode($user->email) . "&avatar=" . urlencode($user->avatar) . "&name=" . urlencode($user->name) . "&bio=" . urlencode($user->bio) . "&phone=" . urlencode($user->phone) . "&created_at=" . urlencode($user->created_at->toIso8601String()));

        //return redirect("http://localhost:5173/");
    }
}
