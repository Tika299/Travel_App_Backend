<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'identifier' => 'required', // email hoặc phone
            'password'   => 'required|string',
        ]);

        // Tìm người dùng theo email hoặc phone
        $user = User::where('email', $request->identifier)
            ->orWhere('phone', $request->identifier)
            ->first();

        // Nếu không tồn tại hoặc sai mật khẩu
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        // (Tuỳ chọn) Kiểm tra trạng thái tài khoản nếu có
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Tài khoản của bạn đang bị tạm khóa.',
            ], 403);
        }

        // Tạo token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Trả về thông tin user (ẩn một số trường)
        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'avatar' => $user->avatar,
                'role'   => $user->role,
            ],
        ]);
    }
}
