<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\AuctionParticipant;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateAuctionStatus extends Command
{
    protected $signature = 'auctions:update-status';
    protected $description = 'Automatically update auction status and handle winners/refunds';

    public function handle()
    {
        $now = Carbon::now();

        //  فتح المزادات اللي بدأ وقتها
        Auction::whereIn('status', ['upcoming', 'pending'])
            ->where('start_time', '<=', $now)
            ->update(['status' => 'opening']);

        //  إنهاء المزادات اللي انتهى وقتها
        $finishedAuctions = Auction::whereIn('status', ['opening', 'pending'])
            ->where('end_time', '<=', $now)
            ->get();

        foreach ($finishedAuctions as $auction) {
            DB::transaction(function () use ($auction) {

                //  تحديد الفائز
                $highestBid = Bid::where('auction_id', $auction->id)
                    ->orderByDesc('amount')
                    ->first();

                if ($highestBid) {
                    $auction->winner_id = $highestBid->bidder_id;
                    $auction->status = 'finished';
                    $auction->save();

                    //  استرجاع المبالغ للمشتركين غير الفائزين
                    $losers = AuctionParticipant::where('auction_id', $auction->id)
                        ->where('user_id', '!=', $highestBid->bidder_id)
                        ->get();

                    foreach ($losers as $loser) {
                        $wallet = Wallet::where('user_id', $loser->user_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$wallet) continue;

                        // إجمالي المبالغ اللي دفعها المستخدم في المزاد ده
                        $refundAmount = Bid::where('auction_id', $auction->id)
                            ->where('bidder_id', $loser->user_id)
                            ->sum('amount');

                        if ($refundAmount > 0) {
                            $before = $wallet->balance;
                            $wallet->balance += $refundAmount;
                            $wallet->save();

                            Transaction::create([
                                'user_id' => $loser->user_id,
                                'wallet_id' => $wallet->id,
                                'auction_id' => $auction->id,
                                'type' => 'refund',
                                'amount' => $refundAmount,
                                'status' => 'success',
                                'balance_before' => $before,
                                'balance_after' => $wallet->balance,
                                'description' => 'Automatic refund for losing bids',
                            ]);
                        }
                    }

                    $this->info("Auction #{$auction->id} finished — Winner: User #{$highestBid->bidder_id}");
                } else {
                    // لا يوجد مزايدات
                    $auction->status = 'finished';
                    $auction->save();
                    $this->info("Auction #{$auction->id} finished — No bids placed.");
                }
            });
        }

        $this->info(" Auction statuses and results updated successfully at " . $now);
    }
}
