<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Auction;
use App\Models\Listing;
use App\Models\Bid;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\AuctionParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuctionController extends Controller
{
    //  إنشاء مزاد جديد
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'listing_id' => 'required|exists:listings,id',
                'type' => 'required|in:scheduled,live',
                'start_time' => 'required_if:type,scheduled|nullable|date',
                'end_time' => 'required_if:type,scheduled|nullable|date|after:start_time',
                'live_stream_time' => 'required_if:type,live|nullable|date',
                'starting_price' => 'required|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'min_increment' => 'nullable|numeric|min:1',
                'join_fee' => 'nullable|numeric|min:0',
            ]);

            if (Auction::where('listing_id', $request->listing_id)->exists()) {
                return response()->json(['message' => 'Auction already exists for this listing'], 400);
            }

            $auction = Auction::create($validated);

            return response()->json([
                'message' => 'Auction created successfully',
                'data' => $auction
            ], 201);
        } catch (\Exception $e) {
            Log::error('Auction creation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create auction'], 500);
        }
    }

    //  عرض كل المزادات
    public function index()
    {
        try {
            $auctions = Auction::with('listing')->paginate(10);
            return response()->json($auctions);
        } catch (\Exception $e) {
            Log::error('Failed to fetch auctions', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch auctions'], 500);
        }
    }

    //  عرض تفاصيل مزاد
    public function show($id)
    {
        try {
            $auction = Auction::with(['listing', 'bids'])->findOrFail($id);
            return response()->json($auction);
        } catch (\Exception $e) {
            Log::error('Failed to fetch auction details', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Auction not found'], 404);
        }
    }

    //  انضمام المستخدم للمزاد
    public function join($id)
    {
        try {
            $user = Auth::user();
            $auction = Auction::findOrFail($id);

            if ($auction->status !== 'upcoming') {
                return response()->json(['message' => 'You can only join upcoming auctions'], 400);
            }

            if (AuctionParticipant::where('auction_id', $id)->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'You already joined this auction'], 400);
            }

            DB::transaction(function () use ($user, $auction) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!$wallet || $wallet->balance < $auction->join_fee) {
                    throw new \Exception('Insufficient wallet balance');
                }

                $wallet->balance -= $auction->join_fee;
                $wallet->save();

                AuctionParticipant::create([
                    'auction_id' => $auction->id,
                    'user_id' => $user->id,
                    'join_fee' => $auction->join_fee
                ]);

                $auction->increment('participants_count');

                Transaction::create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'auction_id' => $auction->id,
                    'type' => 'fee',
                    'amount' => $auction->join_fee,
                    'status' => 'success',
                    'balance_before' => $wallet->balance + $auction->join_fee,
                    'balance_after' => $wallet->balance,
                    'description' => 'Auction join fee'
                ]);
            });

            return response()->json(['message' => 'Joined auction successfully']);
        } catch (\Exception $e) {
            Log::error('Auction join failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to join auction: ' . $e->getMessage()], 500);
        }
    }

    //  تقديم مزايدة
    public function bid(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $validated = $request->validate(['amount' => 'required|numeric|min:1']);

            return DB::transaction(function () use ($validated, $id, $user) {
                $auction = Auction::where('id', $id)->lockForUpdate()->firstOrFail();
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!in_array($auction->status, ['opening', 'live'])) {
                    throw new \Exception('Auction not open for bids');
                }

                if ($wallet->balance < $validated['amount']) {
                    throw new \Exception('Insufficient funds');
                }

                if ($validated['amount'] < $auction->current_price + $auction->min_increment) {
                    throw new \Exception('Bid too low');
                }

                $before = $wallet->balance;
                $wallet->balance -= $validated['amount'];
                $wallet->save();

                Bid::create([
                    'auction_id' => $auction->id,
                    'bidder_id' => $user->id,
                    'amount' => $validated['amount']
                ]);

                $auction->current_price = $validated['amount'];
                $auction->save();

                Transaction::create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'auction_id' => $auction->id,
                    'type' => 'bid',
                    'amount' => $validated['amount'],
                    'status' => 'success',
                    'balance_before' => $before,
                    'balance_after' => $wallet->balance,
                    'description' => 'Bid placed in auction'
                ]);

                return response()->json(['message' => 'Bid placed successfully']);
            });
        } catch (\Exception $e) {
            Log::error('Bid failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Bid failed: ' . $e->getMessage()], 500);
        }
    }

    //  إنهاء المزاد (تحديد الفائز)
    public function finish($id)
    {
        try {
            $auction = Auction::findOrFail($id);
            $highestBid = Bid::where('auction_id', $id)->orderByDesc('amount')->first();

            if (!$highestBid) {
                $auction->status = 'finished';
                $auction->save();
                return response()->json(['message' => 'Auction finished, no bids placed']);
            }

            DB::transaction(function () use ($auction, $highestBid) {
                $auction->winner_id = $highestBid->bidder_id;
                $auction->status = 'finished';
                $auction->save();

                $loserBids = Bid::where('auction_id', $auction->id)
                    ->where('bidder_id', '!=', $highestBid->bidder_id)
                    ->get()
                    ->groupBy('bidder_id');

                foreach ($loserBids as $userId => $userBids) {
                    $refundAmount = $userBids->sum('amount');
                    $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

                    if ($refundAmount > 0 && $wallet) {
                        $before = $wallet->balance;
                        $wallet->balance += $refundAmount;
                        $wallet->save();

                        Transaction::create([
                            'user_id' => $userId,
                            'wallet_id' => $wallet->id,
                            'auction_id' => $auction->id,
                            'type' => 'refund',
                            'amount' => $refundAmount,
                            'status' => 'success',
                            'description' => 'Refund for losing auction',
                            'balance_before' => $before,
                            'balance_after' => $wallet->balance,
                        ]);
                    }
                }
            });

            return response()->json(['message' => 'Auction finished successfully and refunds processed']);
        } catch (\Exception $e) {
            Log::error('Finish auction failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to finish auction: ' . $e->getMessage()], 500);
        }
    }

    //  بدء المزاد (Live)
    public function start($id)
    {
        try {
            $user = Auth::user();

            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Only admin can start auctions'], 403);
            }

            $auction = Auction::findOrFail($id);

            if ($auction->status !== 'upcoming') {
                return response()->json(['message' => 'Auction already started or finished'], 400);
            }

            $auction->status = 'live';
            $auction->save();

            return response()->json([
                'message' => 'Auction started successfully',
                'auction_id' => $auction->id,
                'status' => $auction->status
            ]);
        } catch (\Exception $e) {
            Log::error('Start auction failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to start auction: ' . $e->getMessage()], 500);
        }
    }
}
