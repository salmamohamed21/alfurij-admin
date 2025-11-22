<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Models\User;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// ============================
//  Email Verification Routes (Web Routes)
// ============================

//  تفعيل البريد الإلكتروني (Web Response للـ Frontend)
Route::get('/email/verify/{id}/{hash}', function ($id, $hash, Request $request) {
    $user = \App\Models\User::find($id);

    //  تحقق إن المستخدم موجود
    if (!$user) {
        return redirect('http://localhost:3000/email-verification?error=user_not_found');
    }

    //  تحقق من أن الـ hash يطابق إيميله
    if (!hash_equals(sha1($user->email), $hash)) {
        return redirect('http://localhost:3000/email-verification?error=invalid_link');
    }

    //  لو البريد مفعل بالفعل
    if ($user->hasVerifiedEmail()) {
        \Illuminate\Support\Facades\Log::info('Email verification attempted for already verified user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => $user->email_verified_at,
        ]);
        return redirect('http://localhost:3000/email-verification?token=' . $user->id . '.' . $hash . '&already_verified=true&verified_at=' . urlencode($user->email_verified_at->format('Y-m-d H:i:s')));
    }

    //  تفعيل البريد
    $user->markEmailAsVerified();

    //  إرسال إشعار للمستخدم
    $user->notify(new \App\Notifications\GeneralNotification(
        'تفعيل البريد الإلكتروني',
        'تم تفعيل بريدك الإلكتروني بنجاح ✅'
    ));

    //  توجيه لصفحة التأكيد في الفرونت
    return redirect('http://localhost:3000/email-verification?token=' . $user->id . '.' . $hash);
})->name('verification.verify');

// ============================
//  Admin Routes
// ============================

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm']);
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
});

// ============================
//  Protected Admin Routes
// ============================

Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminAuthController::class, 'dashboard']);
    Route::resource('/listings', ListingController::class);
    Route::resource('/auctions', AuctionController::class);
    Route::resource('/purchases', PurchaseController::class);
    Route::resource('/wallets', WalletController::class);
    Route::resource('/users', UserController::class);
});
