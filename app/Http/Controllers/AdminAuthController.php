<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    //  إنشاء حساب أدمن جديد
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $admin = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => bcrypt($validated['password']),
            'role' => 'admin',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Admin account created successfully',
            'admin' => $admin
        ], 201);
    }

 public function login(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $admin = User::where('email', $validated['email'])->first();

    if (!$admin || !Hash::check($validated['password'], $admin->password)) {
        return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
    }

    //  السماح فقط للأدمن
    if ($admin->role !== 'admin') {
        return response()->json(['message' => 'غير مصرح لك بالدخول إلى لوحة الأدمن.'], 403);
    }

    //  هنا حذفنا التحقق من email_verified_at
    // لأن الأدمن يمكنه الدخول حتى لو البريد غير مفعّل

    $token = $admin->createToken('admin_token')->plainTextToken;

    return response()->json([
        'message' => 'تم تسجيل الدخول بنجاح',
        'token' => $token,
        'admin' => $admin
    ], 200);
}


    //  تسجيل خروج الأدمن
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Admin logged out successfully']);
    }
}
