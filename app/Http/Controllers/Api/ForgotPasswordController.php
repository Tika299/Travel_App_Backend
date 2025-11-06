<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Models\PasswordResetCode;
use App\Models\User;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $code = random_int(100000, 999999);

        PasswordResetCode::updateOrCreate(
            ['email' => $request->email],
            ['code' => $code, 'expires_at' => Carbon::now()->addMinutes(1)]
        );

        Mail::raw("Mã khôi phục mật khẩu của bạn là: $code", function ($message) use ($request) {
            $message->to($request->email)->subject("Mã khôi phục mật khẩu");
        });

        return response()->json(['message' => 'Đã gửi mã khôi phục về email']);
    }

    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required'
        ]);

        $record = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Mã không hợp lệ hoặc đã hết hạn'], 400);
        }

        return response()->json(['message' => 'Mã hợp lệ']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Mã không hợp lệ hoặc đã hết hạn'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['message' => 'Người dùng không tồn tại'], 404);

        $user->password = Hash::make($request->password);
        $user->save();

        $record->delete();

        return response()->json(['message' => 'Đặt lại mật khẩu thành công']);
    }

//xác thực code
    public function verifyCode(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'code' => 'required|digits:6',
    ]);

    $record = PasswordResetCode::where('email', $request->email)
        ->where('code', $request->code)
        ->where('expires_at', '>', now())
        ->first();

    if (!$record) {
        return response()->json(['message' => 'Mã xác thực không đúng hoặc đã hết hạn'], 400);
    }

    return response()->json(['message' => 'Xác thực thành công']);
}

}
