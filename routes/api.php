<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController ;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\BannerController;
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
Route::get('/auth/verify-email', function (Request $request) {
    $token = $request->query('token');

    if (!$token) {
        return response()->json(['message' => 'Token is required'], 400);
    }

    // Parse token to get user ID (assuming token format is user_id.verification_hash)
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return response()->json(['message' => 'Invalid token format'], 400);
    }

    $userId = $parts[0];
    $hash = $parts[1];

    $user = \App\Models\User::find($userId);

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
        \Illuminate\Support\Facades\Log::info('Email verification attempted for already verified user via API', [
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => $user->email_verified_at,
        ]);
        return response()->json([
            'message' => 'تم تفعيل البريد مسبقًا.',
            'verified_at' => $user->email_verified_at->format('Y-m-d H:i:s'),
            'already_verified' => true
        ], 200);
    }

    //  تفعيل البريد
    $user->markEmailAsVerified();

    //  إرسال إشعار للمستخدم
    $user->notify(new \App\Notifications\GeneralNotification(
        'تفعيل البريد الإلكتروني',
        'تم تفعيل بريدك الإلكتروني بنجاح ✅'
    ));

    //  إرسال رسالة نجاح بصيغة جاهزة للفرونت
    return response()->json([
        'message' => 'تم تفعيل البريد الإلكتروني بنجاح 🎉',
        'user' => $user
    ], 200);
})->name('verification.verify');

// ============================
//  User Route
// ============================

//  المستخدم
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/user/update', [UserController::class, 'update']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);
});

// ============================
//  Admin Authentication Routes
// ============================

Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/admin/logout', [AdminAuthController::class, 'logout']);
// ============================
//  Listing Route
// ============================

// Public route for viewing listings
Route::get('/listings', [ListingController::class, 'publicIndex']);

// Admin route for viewing all listings
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/listings', [ListingController::class, 'adminIndex']);
});

//  عرض كل الإعلانات (مع فلاتر اختيارية)
//  إدارة الإعلانات
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('listings', ListingController::class)->except(['index']);
        Route::post('/listings/{listing}/approve', [ListingController::class, 'approve']);
        Route::post('/listings/{listing}/reject', [ListingController::class, 'reject']);
});

// ============================
//  Auction Route
// ============================

// Public route for viewing auctions
Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');

//  إنشاء مزاد جديد
//  المستخدمين
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auctions/{id}', [AuctionController::class, 'show'])->name('auctions.show');
    Route::post('/auctions/{id}/join', [AuctionController::class, 'join'])->name('auctions.join');
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'bid'])->name('auctions.bid');
    Route::get('/auctions/{id}/bids', [AuctionController::class, 'bids'])->name('auctions.bids');
});

//  الأدمن
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/auctions', [AuctionController::class, 'adminIndex'])->name('auctions.adminIndex');
    Route::post('/auctions', [AuctionController::class, 'store'])->name('auctions.store');
    Route::put('/auctions/{id}', [AuctionController::class, 'update'])->name('auctions.update');
    Route::post('/auctions/{id}/finish', [AuctionController::class, 'finish'])->name('auctions.finish');
    Route::post('/auctions/{id}/start', [AuctionController::class, 'start']);

    // Stream management routes
    Route::post('/auctions/{auctionId}/streams', [AuctionController::class, 'storeStream']);
    Route::get('/auctions/{auctionId}/streams', [AuctionController::class, 'getStreams']);
    Route::get('/auctions/{auctionId}/active-stream', [AuctionController::class, 'activeStream']);
    Route::post('/streams/{streamId}/start', [AuctionController::class, 'startLive']);
    Route::post('/streams/{streamId}/stop', [AuctionController::class, 'stopLive']);
    Route::put('/auctions/{auctionId}/streams/{streamId}', [AuctionController::class, 'updateStream']);
    Route::delete('/auctions/{auctionId}/streams/{streamId}', [AuctionController::class, 'deleteStream']);

});

// ============================
//  Purchase Route
// ============================

//  المستخدم
Route::middleware('auth:sanctum')->post('/purchase', [PurchaseController::class, 'purchase']);

// ============================
//  Wallet Route
// ============================

//  المستخدم

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/topup', [WalletController::class, 'topup']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']); // ✅ سجل المعاملات
});

// ============================
//  Models Route
// ============================

// Public route for viewing models
Route::get('/models', [ModelController::class, 'publicIndex']);

// Admin routes for managing models
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/models', [ModelController::class, 'addModel']);
    Route::put('/models/{id}', [ModelController::class, 'update']);
    Route::delete('/models/{id}', [ModelController::class, 'destroy']);
});

// Admin routes for managing banners
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('banners', BannerController::class)->except(['index']);
    Route::post('/banners/update-order', [BannerController::class, 'updateOrder']);
});

// Public route for viewing banners
Route::get('/banners', [BannerController::class, 'index']);

// ============================
//  Notifications Route
// ============================

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', function () {
        $notifications = auth()->user()->notifications()->orderBy('created_at', 'desc')->get();

        // Transform notifications to match frontend expectations
        $transformedNotifications = $notifications->map(function ($notification) {
            $data = json_decode($notification->data, true);
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $data['title'] ?? 'إشعار جديد',
                'message' => $data['message'] ?? '',
                'timestamp' => $notification->created_at,
                'read' => !is_null($notification->read_at),
            ];
        });

        return response()->json($transformedNotifications);
    });

    Route::post('/notifications/{id}/read', function ($id) {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    });

    Route::post('/notifications/mark-all-read', function () {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    });
});
