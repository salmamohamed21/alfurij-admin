<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Auction;
use App\Models\Listing;
use App\Models\Bid;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\AuctionParticipant;
use App\Models\AuctionStream;
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
            $auctions = Auction::with('listing')->whereHas('listing', function($query) {
                $query->where('approval_status', 'approved');
            })->paginate(10);
            return response()->json($auctions);
        } catch (\Exception $e) {
            Log::error('Failed to fetch auctions', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch auctions'], 500);
        }
    }

    //  عرض كل المزادات للأدمن
    public function adminIndex()
    {
        try {
            $auctions = Auction::with(['listing', 'winner'])->paginate(10);
            return response()->json($auctions);
        } catch (\Exception $e) {
            Log::error('Failed to fetch auctions for admin', ['error' => $e->getMessage()]);
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

                // إرسال إشعار للمستخدم
                $user->notify(new \App\Notifications\GeneralNotification(
                    'خصم رصيد أثناء المزايدة',
                    'تم خصم مبلغ ' . $auction->join_fee . ' ريال من محفظتك للمشاركة في المزاد.'
                ));
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
            $validated = $request->validate(['points' => 'required|numeric|min:1']);

            return DB::transaction(function () use ($validated, $id, $user) {
                $auction = Auction::where('id', $id)->lockForUpdate()->firstOrFail();
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!in_array($auction->status, ['opening', 'live'])) {
                    throw new \Exception('Auction not open for bids');
                }

                $amount = points_to_sar($validated['points']); // تحويل النقاط إلى ريال

                if ($wallet->balance < $amount) {
                    throw new \Exception('Insufficient funds');
                }

                if ($amount < $auction->current_price + $auction->min_increment) {
                    throw new \Exception('Bid too low');
                }

                $before = $wallet->balance;
                $wallet->balance -= $amount;
                $wallet->save();

                Bid::create([
                    'auction_id' => $auction->id,
                    'bidder_id' => $user->id,
                    'amount' => $amount
                ]);

                $auction->current_price = $amount;
                $auction->save();

                Transaction::create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'auction_id' => $auction->id,
                    'type' => 'bid',
                    'amount' => $amount,
                    'status' => 'success',
                    'balance_before' => $before,
                    'balance_after' => $wallet->balance,
                    'description' => 'Bid placed in auction: ' . $validated['points'] . ' points (' . $amount . ' SAR)'
                ]);

                // إرسال إشعار للمستخدم
                $user->notify(new \App\Notifications\GeneralNotification(
                    'خصم رصيد أثناء المزايدة',
                    'تم خصم ' . $validated['points'] . ' نقطة (' . $amount . ' ريال) من محفظتك للمزايدة.'
                ));

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

                        $refundPoints = sar_to_points($refundAmount);
                        Transaction::create([
                            'user_id' => $userId,
                            'wallet_id' => $wallet->id,
                            'auction_id' => $auction->id,
                            'type' => 'refund',
                            'amount' => $refundAmount,
                            'status' => 'success',
                            'description' => 'Refund for losing auction: ' . $refundPoints . ' points (' . $refundAmount . ' SAR)',
                            'balance_before' => $before,
                            'balance_after' => $wallet->balance,
                        ]);

                        // إرسال إشعار للمستخدم
                        $user = \App\Models\User::find($userId);
                        if ($user) {
                            $user->notify(new \App\Notifications\GeneralNotification(
                                'استرجاع المبلغ',
                                'تم استرجاع ' . $refundPoints . ' نقطة (' . $refundAmount . ' ريال) إلى محفظتك.'
                            ));
                        }
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

            // إرسال إشعار لجميع المشاركين
            $participants = AuctionParticipant::where('auction_id', $auction->id)->get();
            foreach ($participants as $participant) {
                $participant->user->notify(new \App\Notifications\GeneralNotification(
                    'بدء المزاد',
                    'المزاد بدأ الآن! شارك في المزايدة 🚀'
                ));
            }

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

    //  تحديث حالة المزاد يدوياً (للاختبار)
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can update auction status'], 403);
            }

            $validated = $request->validate([
                'status' => 'required|in:upcoming,opening,live,finished'
            ]);

            $auction = Auction::findOrFail($id);
            $auction->status = $validated['status'];
            $auction->save();

            return response()->json([
                'message' => 'Auction status updated successfully',
                'auction_id' => $auction->id,
                'status' => $auction->status
            ]);
        } catch (\Exception $e) {
            Log::error('Update auction status failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update auction status: ' . $e->getMessage()], 500);
        }
    }

    //  تحديث المزاد
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can update auctions'], 403);
            }

            $validated = $request->validate([
                'type' => 'nullable|in:scheduled,live',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after:start_time',
                'live_stream_time' => 'nullable|date',
                'starting_price' => 'nullable|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'min_increment' => 'nullable|numeric|min:1',
                'join_fee' => 'nullable|numeric|min:0',
                'status' => 'nullable|in:upcoming,opening,live,finished',
                'is_streaming' => 'nullable|boolean',
            ]);

            $auction = Auction::findOrFail($id);
            $auction->update($validated);

            return response()->json([
                'message' => 'Auction updated successfully',
                'data' => $auction
            ]);
        } catch (\Exception $e) {
            Log::error('Update auction failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update auction: ' . $e->getMessage()], 500);
        }
    }

    // Store new stream for auction
    public function storeStream(Request $request, $auctionId)
    {
        try {
            $user = Auth::user();
            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can manage streams'], 403);
            }

            $validated = $request->validate([
                'platform' => 'required|string|max:50',
                'watch_url' => 'required|url',
                'embed_url' => 'nullable|url',
                'is_active' => 'boolean'
            ]);

            $auction = Auction::findOrFail($auctionId);

            $stream = AuctionStream::create([
                'auction_id' => $auction->id,
                'platform' => $validated['platform'],
                'watch_url' => $validated['watch_url'],
                'embed_url' => $validated['embed_url'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            return response()->json([
                'message' => 'Stream created successfully',
                'data' => $stream
            ], 201);
        } catch (\Exception $e) {
            Log::error('Store stream failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create stream: ' . $e->getMessage()], 500);
        }
    }

    // Get streams for auction
    public function getStreams($auctionId)
    {
        try {
            $auction = Auction::findOrFail($auctionId);
            $streams = $auction->streams()->orderBy('created_at', 'desc')->get();

            return response()->json($streams);
        } catch (\Exception $e) {
            Log::error('Get streams failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to get streams'], 500);
        }
    }

    // Update stream
    public function updateStream(Request $request, $auctionId, $streamId)
    {
        try {
            $user = Auth::user();
            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can manage streams'], 403);
            }

            $validated = $request->validate([
                'platform' => 'sometimes|in:youtube,facebook,instagram,tiktok,snapchat',
                'stream_url' => 'sometimes|string',
                'embed_url' => 'nullable|string',
                'status' => 'sometimes|in:scheduled,live,finished'
            ]);

            $stream = AuctionStream::where('auction_id', $auctionId)->findOrFail($streamId);
            $stream->update($validated);

            return response()->json([
                'message' => 'Stream updated successfully',
                'data' => $stream
            ]);
        } catch (\Exception $e) {
            Log::error('Update stream failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update stream: ' . $e->getMessage()], 500);
        }
    }

    // Delete stream
    public function deleteStream($auctionId, $streamId)
    {
        try {
            $user = Auth::user();
            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can manage streams'], 403);
            }

            $stream = AuctionStream::where('auction_id', $auctionId)->findOrFail($streamId);
            $stream->delete();

            return response()->json(['message' => 'Stream deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Delete stream failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete stream: ' . $e->getMessage()], 500);
        }
    }

    // Start live stream
    public function startLive($streamId)
    {
        try {
            $user = Auth::user();
            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can manage streams'], 403);
            }

            $stream = AuctionStream::findOrFail($streamId);

            $stream->update([
                'status' => 'live',
                'live_start_time' => now()
            ]);

            // Update auction is_streaming flag
            $auction = $stream->auction;
            $auction->update(['is_streaming' => true]);

            return response()->json([
                'message' => 'Live started',
                'data' => $stream
            ]);
        } catch (\Exception $e) {
            Log::error('Start live failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to start live: ' . $e->getMessage()], 500);
        }
    }

    // Stop live stream
    public function endStream($auctionId)
    {
        try {
            $user = Auth::user();
            if (!in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can manage streams'], 403);
            }

            $auction = Auction::findOrFail($auctionId);

            if (!$auction->is_streaming) {
                return response()->json(['message' => 'No active stream found'], 404);
            }

            $auction->update([
                'is_streaming' => false,
            ]);

            return response()->json([
                'message' => 'Live stopped',
                'data' => $auction
            ]);
        } catch (\Exception $e) {
            Log::error('Stop live failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to stop live: ' . $e->getMessage()], 500);
        }
    }

    // Get active stream for auction
    public function getActiveStream($auctionId)
    {
        try {
            $auction = Auction::findOrFail($auctionId);

            if (!$auction->is_streaming) {
                return response()->json(['message' => 'No active stream found'], 404);
            }

            return response()->json([
                'stream_url' => $auction->stream_url,
                'is_streaming' => $auction->is_streaming,
            ]);
        } catch (\Exception $e) {
            Log::error('Get active stream failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to get active stream'], 500);
        }
    }
}
