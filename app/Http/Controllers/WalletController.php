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
            $wallet = Wallet::where('user_id', Auth::id())->first();

            if (!$wallet) {
                return response()->json(['message' => 'Wallet not found'], 404);
            }

            return response()->json([
                'balance' => $wallet->balance,
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
                'amount' => 'required|numeric|min:1'
            ]);

            DB::transaction(function () use ($validated) {
                $user = Auth::user();

                // إنشاء المحفظة لو مش موجودة
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    ['balance' => 0, 'currency' => 'SAR']
                );

                $before = $wallet->balance;
                $wallet->balance += $validated['amount'];
                $wallet->save();

                Transaction::create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'type' => 'topup',
                    'amount' => $validated['amount'],
                    'balance_before' => $before,
                    'balance_after' => $wallet->balance,
                    'status' => 'success',
                    'description' => 'Wallet top-up',
                ]);
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

    //  عرض سجل العمليات المالية للمستخدم
    public function transactions()
    {
        try {
            $wallet = Wallet::where('user_id', Auth::id())->first();

            if (!$wallet) {
                return response()->json(['message' => 'Wallet not found'], 404);
            }

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
