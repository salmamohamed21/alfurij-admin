<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    //  تنفيذ عملية الشراء (Buy Now)
    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'listing_id' => 'required|exists:listings,id',
        ]);

        try {
            $buyer = Auth::user();
            $listing = Listing::findOrFail($validated['listing_id']);

            if (!$listing->seller) {
                return response()->json(['message' => 'Listing seller not found'], 400);
            }

            if ($listing->ad_type !== 'ad' || !$listing->buy_now) {
                return response()->json(['message' => 'This listing is not available for direct purchase'], 400);
            }

            if ($listing->approval_status !== 'approved') {
                return response()->json(['message' => 'Listing not approved yet'], 400);
            }

            if ($listing->status !== 'active') {
                return response()->json(['message' => 'This listing is not available for purchase'], 400);
            }

            //  التحقق من رصيد المحفظة قبل البدء في المعاملة
            $buyerWalletCheck = Wallet::where('user_id', $buyer->id)->first();
            if (!$buyerWalletCheck || $buyerWalletCheck->balance < $listing->price) {
                return response()->json(['message' => 'Insufficient wallet balance to complete the purchase.'], 400);
            }

            DB::transaction(function () use ($buyer, $listing) {
                //  قفل الصفوف أثناء الخصم
                $buyerWallet = Wallet::where('user_id', $buyer->id)->lockForUpdate()->first();

                //  إعادة التحقق من الرصيد بعد القفل (للتأكد من عدم التغيير)
                if (!$buyerWallet || $buyerWallet->balance < $listing->price) {
                    abort(400, 'Insufficient wallet balance to complete the purchase.');
                }

                $seller = $listing->seller;
                $sellerWallet = Wallet::firstOrCreate(
                    ['user_id' => $seller->id],
                    ['balance' => 0, 'currency' => 'SAR']
                );

                //  تحديد الجهة المستفيدة (Admin أو User)
                if ($seller->role === 'admin') {
                    // الأموال تذهب لمحفظة الشركة
                    $companyWallet = $sellerWallet;
                    $receiverId = $seller->id;
                } else {
                    // الأموال تذهب للبائع المستخدم
                    $companyWallet = $sellerWallet;
                    $receiverId = $seller->id;
                }

                //  خصم المبلغ من المشتري
                $beforeBuyer = $buyerWallet->balance;
                $buyerWallet->balance -= $listing->price;
                $buyerWallet->save();

                //  إضافة المبلغ للبائع
                $beforeSeller = $companyWallet->balance;
                $companyWallet->balance += $listing->price;
                $companyWallet->save();

                //  تحديث حالة الإعلان
                $listing->update(['status' => 'sold']);

                //  إنشاء المعاملات
                Transaction::create([
                    'user_id' => $buyer->id,
                    'related_user_id' => $receiverId,
                    'wallet_id' => $buyerWallet->id,
                    'type' => 'purchase',
                    'amount' => $listing->price,
                    'status' => 'success',
                    'balance_before' => $beforeBuyer,
                    'balance_after' => $buyerWallet->balance,
                    'description' => 'Purchase of listing #' . $listing->id,
                ]);

                Transaction::create([
                    'user_id' => $receiverId,
                    'related_user_id' => $buyer->id,
                    'wallet_id' => $companyWallet->id,
                    'type' => 'sale_income',
                    'amount' => $listing->price,
                    'status' => 'success',
                    'balance_before' => $beforeSeller,
                    'balance_after' => $companyWallet->balance,
                    'description' => 'Income from sale of listing #' . $listing->id,
                ]);

                // إرسال إشعار للمشتري
                $buyer->notify(new \App\Notifications\GeneralNotification(
                    'تم الشراء بنجاح',
                    'تم شراء الإعلان بمبلغ ' . $listing->price . ' ريال بنجاح.'
                ));

                // إرسال إشعار للبائع
                $seller->notify(new \App\Notifications\GeneralNotification(
                    'تم بيع الإعلان',
                    'تم بيع إعلانك بمبلغ ' . $listing->price . ' ريال.'
                ));
            });

            return response()->json(['message' => 'Purchase completed successfully']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Purchase failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
