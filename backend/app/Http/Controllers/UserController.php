<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // ✅ THÊM DÒNG NÀY
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function getUserInfo()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'phone' => $user->phone,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at
        ]);
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->bio = $request->bio;
        $user->save();

        return response()->json(['message' => 'Cập nhật thành công']);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->hasFile('avatar')) {
            $image = $request->file('avatar');

            $filename = uniqid() . '.' . $image->getClientOriginalExtension();

            $frontendPath = base_path('../frontend/public/img'); // 🟢 nơi chứa ảnh trong React
            $image->move($frontendPath, $filename);

            $user->avatar = 'img/' . $filename;
            $user->save();

            return response()->json([
                'message' => 'Cập nhật ảnh đại diện thành công',
                'avatar_url' => '/img/' . $filename, // ✅ trả về đường dẫn tương đối
            ]);
        }

        return response()->json(['message' => 'Không tìm thấy ảnh'], 400);
    }

    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    public function stats()
    {
        $total = User::count();
        $active = User::where('status', 'active')->count();
        $inactive = User::where('status', 'inactive')->count();
        $today = User::whereDate('created_at', today())->count();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'today' => $today,
        ]);
    }


    // app/Http/Controllers/UserController.php
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    public function deleteMultiple(Request $request)
    {
        $ids = $request->input('ids');
        User::whereIn('id', $ids)->delete();
        return response()->json(['message' => 'Selected users deleted successfully']);
    }

    public function updateAdmin(Request $request, $id)
{
    $admin = Auth::user();

    // Kiểm tra nếu admin không có quyền thì chặn
    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Không có quyền'], 403);
    }

    $user = User::find($id);
    if (!$user) {
        return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
    }

    // Validate
    $validator = Validator::make($request->all(), [
        'name' => 'required|string',
        'email' => 'required|email|unique:users,email,' . $id,
        'phone' => 'nullable|string',
        'status' => 'in:active,inactive',
        'bio' => 'nullable|string',
        'role' => 'in:user,admin,moderator',
        'password' => 'nullable|min:6',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Cập nhật
    $user->name = $request->name;
    $user->email = $request->email;
    $user->phone = $request->phone;
    $user->status = $request->status;
    $user->bio = $request->bio;
    $user->role = $request->role;

    if ($request->filled('password')) {
        $user->password = bcrypt($request->password);
    }

    $user->save();

    return response()->json(['message' => 'Cập nhật thành công']);
}

public function updateAvatarByAdmin(Request $request, $id)
{
    $admin = Auth::user();

    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Không có quyền'], 403);
    }

    $request->validate([
        'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
    ]);

    $user = User::find($id);
    if (!$user) {
        return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
    }

    if ($request->hasFile('avatar')) {
        $image = $request->file('avatar');
        $filename = uniqid() . '.' . $image->getClientOriginalExtension();
        $frontendPath = base_path('../frontend/public/img');
        $image->move($frontendPath, $filename);

        $user->avatar = 'img/' . $filename;
        $user->save();

        return response()->json([
            'message' => 'Cập nhật ảnh đại diện thành công',
            'avatar_url' => '/img/' . $filename,
        ]);
    }

    return response()->json(['message' => 'Không tìm thấy ảnh'], 400);
}
// thêm user admin
public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6', // confirmPassword phải được gửi kèm
        'phone' => 'nullable|string|max:20',
        'status' => ['required', Rule::in(['active', 'inactive'])],
        'role' => ['required', Rule::in(['user', 'admin', 'moderator'])],
        'bio' => 'nullable|string',
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'phone' => $request->phone,
        'status' => $request->status,
        'bio' => $request->bio,
        'role' => $request->role,
    ]);

    return response()->json([
        'message' => 'Tạo người dùng thành công',
        'user' => $user,
    ], 201);
}

}
