<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
    //  تسجيل مستخدم جديد
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|min:6|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            // إرسال رابط التحقق
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Registration successful! Please check your email for verification link.',
                'user' => $user
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('AuthController@register failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Registration failed. Please try again later.'
            ], 500);
        }
    }

    //  تسجيل الدخول
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            // استثناء الأدمن من شرط التفعيل
            if ($user->role !== 'admin' && !$user->hasVerifiedEmail()) {
                return response()->json(['message' => 'Please verify your email first'], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('AuthController@login failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Login failed. Please try again later.'
            ], 500);
        }
    }

    //  تسجيل الخروج
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return response()->json(['message' => 'Logged out successfully']);

        } catch (\Exception $e) {
            Log::error('AuthController@logout failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Logout failed. Please try again later.'], 500);
        }
    }
}
