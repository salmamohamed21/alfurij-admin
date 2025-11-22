<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    //  عرض رصيد المستخدم الحالي
    public function show()
    {
        try {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => Auth::id()],
                ['balance' => 0, 'currency' => 'SAR']
            );

            return response()->json([
                'balance_sar' => $wallet->balance,
                'balance_points' => sar_to_points($wallet->balance),
                'currency' => $wallet->currency,
            ]);
        } catch (\Exception $e) {
            Log::error('WalletController@show failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch wallet balance'], 500);
        }
    }

    //  شحن المحفظة (محاكاة عملية دفع)
    public function topup(Request $request)
    {
        try {
            $validated = $request->validate([
                'points' => 'required|numeric|min:1',
                'card_number' => 'nullable|string|max:19',
                'expiry_month' => 'nullable|string|max:2',
                'expiry_year' => 'nullable|string|max:4',
                'cvv' => 'nullable|string|max:4',
                'cardholder_name' => 'nullable|string|max:255',
                'save_card' => 'nullable|boolean'
            ]);

            DB::transaction(function () use ($validated) {
                $user = Auth::user();

                // حفظ بيانات البطاقة إذا طلب المستخدم ذلك
                if ($validated['save_card'] && $validated['card_number']) {
                    $savedCards = $user->saved_cards ? json_decode($user->saved_cards, true) : [];
                    $cardData = [
                        'card_number' => substr($validated['card_number'], -4), // حفظ آخر 4 أرقام فقط
                        'expiry_month' => $validated['expiry_month'],
                        'expiry_year' => $validated['expiry_year'],
                        'cardholder_name' => $validated['cardholder_name'],
                        'created_at' => now()->toISOString()
                    ];
                    $savedCards[] = $cardData;
                    $user->saved_cards = json_encode($savedCards);
                    $user->save();
                }

                // محاكاة الاتصال بـ API الدفع (Visa, MasterCard, Mada)
                // هنا يجب استبدال هذا الكود بالاتصال الحقيقي بـ API الدفع
                $paymentSuccess = $this->processPayment($validated);

                if (!$paymentSuccess) {
                    throw new \Exception('Payment processing failed');
                }

                // إنشاء المحفظة لو مش موجودة
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    ['balance' => 0, 'currency' => 'SAR']
                );

                // تحويل النقاط إلى ريال
                $sarAmount = points_to_sar($validated['points']);

                $before = $wallet->balance;
                $wallet->balance += $sarAmount;
                $wallet->save();

                // إنشاء سجل الدفع
                $payment = \App\Models\Payment::create([
                    'user_id' => $user->id,
                    'amount' => $sarAmount,
                    'currency' => 'SAR',
                    'status' => 'succeeded',
                    'provider' => 'visa_mastercard_mada', // يمكن تحديده حسب نوع البطاقة
                    'provider_payment_id' => 'simulated_' . time()
                ]);

                Transaction::create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'payment_id' => $payment->id,
                    'type' => 'topup',
                    'amount' => $sarAmount,
                    'balance_before' => $before,
                    'balance_after' => $wallet->balance,
                    'status' => 'success',
                    'description' => 'Wallet top-up: ' . $validated['points'] . ' points (' . $sarAmount . ' SAR)',
                ]);

                // إرسال إشعار للمستخدم
                $user->notify(new \App\Notifications\GeneralNotification(
                    'شحن المحفظة',
                    'تم شحن محفظتك بـ ' . $validated['points'] . ' نقطة (' . $sarAmount . ' ريال) بنجاح.'
                ));
            });

            return response()->json([
                'message' => 'Wallet topped up successfully'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // لو البيانات المدخلة غير صحيحة
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);

        } catch (\Exception $e) {
            Log::error('WalletController@topup failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to top up wallet'], 500);
        }
    }

    // محاكاة معالجة الدفع (يجب استبدالها بالاتصال الحقيقي بـ API الدفع)
    private function processPayment($data)
    {
        // هنا يتم محاكاة عملية الدفع
        // في التطبيق الحقيقي، يجب الاتصال بـ API الدفع المناسب (Visa, MasterCard, Mada)

        // محاكاة نجاح الدفع
        return true;
    }

    //  عرض سجل العمليات المالية للمستخدم
    public function transactions()
    {
        try {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => Auth::id()],
                ['balance' => 0, 'currency' => 'SAR']
            );

            $transactions = $wallet->transactions()
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'wallet_balance' => $wallet->balance,
                'transactions' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('WalletController@transactions failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch transactions'], 500);
        }
    }
}
