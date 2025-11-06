<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendVerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    //
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $otp = rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(5);

        DB::table('email_verifications')->updateOrInsert(
            ['email' => $request->email],
            ['otp' => $otp, 'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now()]
        );

        Mail::to($request->email)->send(new SendVerificationCodeMail($otp));

        return response()->json(['message' => 'Mã xác thực đã được gửi đến email']);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            'phone' => 'nullable|string',
            'otp' => 'required'
        ]);

        $record = DB::table('email_verifications')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Mã xác thực không hợp lệ hoặc đã hết hạn'], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),

            // Cấu hình thêm:
            'status' => 'active',
            'role' => 'user', // mặc định người dùng
            'avatar' => 'avatars/default.png', // ảnh mặc định
            'bio' => 'Tôi là người dùng mới.',
            'remember_token' => Str::random(10),
        ]);

        DB::table('email_verifications')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Đăng ký thành công', 'user' => $user]);
    }
}
