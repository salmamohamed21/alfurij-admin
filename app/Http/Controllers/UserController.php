<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    // عرض بروفايل المستخدم
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'avatar' => $user->avatar ? \Storage::url($user->avatar) : null,
                    'email_verified_at' => $user->email_verified_at,
                    'is_super_admin' => $user->role === \App\Models\User::ROLE_SUPER_ADMIN,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserController@show failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve user profile.'
            ], 500);
        }
    }

    // تحديث بروفايل المستخدم
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $data = $request->only(['name', 'email', 'phone']);

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                    \Storage::disk('public')->delete($user->avatar);
                }

                // Store new avatar
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $data['avatar'] = $avatarPath;
            }

            $user->update($data);

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'avatar' => $user->avatar ? \Storage::url($user->avatar) : null,
                    'email_verified_at' => $user->email_verified_at,
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('UserController@update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update profile.'
            ], 500);
        }
    }

    // تغيير كلمة المرور
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|min:6|confirmed',
            ]);

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect.'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'message' => 'Password changed successfully.'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('UserController@changePassword failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to change password.'
            ], 500);
        }
    }
}
