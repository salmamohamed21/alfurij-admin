<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController ;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\WalletController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Models\User;


// ============================
//  Authentication Routes
// ============================

//  تسجيل حساب جديد
Route::post('/auth/register', [AuthController::class, 'register']);

//  تسجيل الدخول
Route::post('/auth/login', [AuthController::class, 'login']);

//  تسجيل الخروج
Route::middleware('auth:sanctum')->post('/auth/logout', [AuthController::class, 'logout']);


// ============================
//  Email Verification Routes
// ============================

//  إعادة إرسال رابط التفعيل بدون توكن
Route::post('/email/resend', function (Request $request) {
    $request->validate([
        'email' => 'required|email'
    ]);

    $user = User::where('email', $request->email)->first();

    //  لو الإيميل مش موجود
    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'البريد الإلكتروني غير مسجل لدينا.'
        ], 404);
    }

    //  لو الإيميل مفعل بالفعل
    if ($user->hasVerifiedEmail()) {
        return response()->json([
            'status' => 'info',
            'message' => 'تم تفعيل البريد الإلكتروني مسبقًا. يمكنك تسجيل الدخول الآن.'
        ], 200);
    }

    //  إرسال رابط التحقق من جديد
    $user->sendEmailVerificationNotification();

    return response()->json([
        'status' => 'success',
        'message' => 'تم إرسال رابط التحقق إلى بريدك الإلكتروني.'
    ], 200);
});

//  تفعيل البريد الإلكتروني (JSON Response للـ Frontend)
Route::get('/email/verify/{id}/{hash}', function ($id, $hash, Request $request) {
    $user = \App\Models\User::find($id);

    //  تحقق إن المستخدم موجود
    if (!$user) {
        return response()->json(['message' => 'المستخدم غير موجود.'], 404);
    }

    //  تحقق من أن الـ hash يطابق إيميله
    if (!hash_equals(sha1($user->email), $hash)) {
        return response()->json(['message' => 'رابط التحقق غير صالح.'], 400);
    }

    //  لو البريد مفعل بالفعل
    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'تم تفعيل البريد مسبقًا.'], 200);
    }

    //  تفعيل البريد
    $user->markEmailAsVerified();

    //  إرسال رسالة نجاح بصيغة جاهزة للفرونت
    return response()->json([
        'message' => 'تم تفعيل البريد الإلكتروني بنجاح 🎉',
        'user' => $user
    ], 200);
})->middleware(['signed'])->name('verification.verify');

// ============================
//  Admin Authentication Routes
// ============================

Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/admin/logout', [AdminAuthController::class, 'logout']);
// ============================
//  Listing Route
// ============================

//  عرض كل الإعلانات (مع فلاتر اختيارية)
//  إدارة الإعلانات
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('listings', ListingController::class);
    Route::post('/listings/{listing}/approve', [ListingController::class, 'approve']);
});
// ============================
//  Auction Route
// ============================

//  إنشاء مزاد جديد
//  المستخدمين
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/{id}', [AuctionController::class, 'show'])->name('auctions.show');
    Route::post('/auctions/{id}/join', [AuctionController::class, 'join'])->name('auctions.join');
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'bid'])->name('auctions.bid');
    Route::get('/auctions/{id}/bids', [AuctionController::class, 'bids'])->name('auctions.bids');
});

//  الأدمن
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/auctions', [AuctionController::class, 'store'])->name('auctions.store');
    Route::post('/auctions/{id}/finish', [AuctionController::class, 'finish'])->name('auctions.finish');
    Route::post('/auctions/{id}/start', [AuctionController::class, 'start']);
    
});

// ============================
//  Wallet Route
// ============================

//  المستخدم

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/topup', [WalletController::class, 'topup']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']); // ✅ سجل المعاملات
});