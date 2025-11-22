<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ListingResource;

class ListingController extends Controller
{
    // Public method to view only approved listings
    public function publicIndex(Request $request)
    {
        try {
            $query = Listing::where('approval_status', 'approved');

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhereHas('seller', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $listings = $query->with('seller')->latest()->paginate(10);

            return ListingResource::collection($listings);
        } catch (\Exception $e) {
            Log::error('ListingController@publicIndex failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch public listings'], 500);
        }
    }

    //  عرض كل الإعلانات (مع فلاتر اختيارية)
    public function index(Request $request)
    {
        try {
            $query = Listing::query();

            // إذا لم يكن المستخدم أدمن، عرض الإعلانات المعتمدة فقط
            if (!in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                $query->where('approval_status', 'approved');
            }

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhereHas('seller', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $listings = $query->with('seller')->latest()->paginate(10);

            return ListingResource::collection($listings);
        } catch (\Exception $e) {
            Log::error('ListingController@index failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch listings'], 500);
        }
    }

    // Admin method to view all listings without approval filter
    public function adminIndex(Request $request)
    {
        try {
            $query = Listing::query();

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhereHas('seller', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $listings = $query->with('seller')->latest()->paginate(10);

            return ListingResource::collection($listings);
        } catch (\Exception $e) {
            Log::error('ListingController@adminIndex failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch admin listings'], 500);
        }
    }

    //  إنشاء إعلان جديد
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'ad_type' => 'required|in:ad,live_auction,scheduled_auction',
                'title' => 'required|string|max:255',
                'category' => 'required|string|max:100',
                'section' => 'nullable|string|max:50',
                'city' => 'required|string|max:100',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'condition' => 'required|string',
                'model' => 'required|string',
                'serial_number' => 'required|string',
                'cabin_type' => 'nullable|string',
                'vehicle_type' => 'nullable|string',
                'engine_capacity' => 'nullable|string',
                'fuel_type' => 'nullable|string',
                'transmission' => 'nullable|string',
                'lights_type' => 'nullable|string',
                'color' => 'nullable|string',
                'length' => 'nullable|numeric',
                'width' => 'nullable|numeric',
                'height' => 'nullable|numeric',
                'kilometers' => 'nullable|numeric|min:0',
                'registration_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'gearbox_brand' => 'nullable|string|max:100',
                'gearbox_type' => 'nullable|string|max:100',
                'other' => 'nullable|array',
                'buy_now' => 'boolean',
                'auction_start_date' => 'nullable|date',
                'auction_start_time' => 'nullable|date_format:H:i',
                'auction_end_date' => 'nullable|date',
                'auction_end_time' => 'nullable|date_format:H:i',
                'files.image' => 'nullable|array|max:10', // Max 10 images
                'files.image.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB per image, added webp
                'files.video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:51200', // 50MB for video, added webm
                'files.pdf' => 'nullable|array|max:5', // Max 5 PDFs
                'files.pdf.*' => 'file|mimes:pdf|max:5120', // 5MB per PDF
            ]);

            // Additional validation for auction types
            if (in_array($validated['ad_type'], ['live_auction', 'scheduled_auction'])) {
                if (empty($validated['auction_start_date']) || empty($validated['auction_start_time'])) {
                    return response()->json(['message' => 'تاريخ ووقت بدء المزاد مطلوبان'], 422);
                }
                if ($validated['ad_type'] === 'scheduled_auction') {
                    if (empty($validated['auction_end_date']) || empty($validated['auction_end_time'])) {
                        return response()->json(['message' => 'تاريخ ووقت انتهاء المزاد مطلوبان'], 422);
                    }
                }
            }

            // Filter out null values to prevent inserting null into non-nullable columns
            $validated = array_filter($validated, fn ($v) => !is_null($v));

            $validated['seller_id'] = Auth::id();
            $validated['approval_status'] = in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN]) ? 'approved' : 'pending';
            $validated['status'] = in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN]) ? 'active' : 'draft'; // Set status to active for admins, draft otherwise

            // Handle file uploads
            $media = [];
            $documents = [];

            // Handle images
            if ($request->hasFile('files.image')) {
                foreach ($request->file('files.image') as $image) {
                    $path = $image->store('listings/images', 'public');
                    $path = str_replace('\\', '/', $path); // Ensure forward slashes for URLs
                    $media[] = $path;
                }
            }

            // Handle video
            if ($request->hasFile('files.video')) {
                $video = $request->file('files.video');
                $path = $video->store('listings/videos', 'public');
                $path = str_replace('\\', '/', $path); // Ensure forward slashes for URLs
                $media[] = $path;
            }

            // Handle PDFs
            if ($request->hasFile('files.pdf')) {
                foreach ($request->file('files.pdf') as $pdf) {
                    $path = $pdf->store('listings/documents', 'public');
                    $path = str_replace('\\', '/', $path); // Ensure forward slashes for URLs
                    $documents[] = $path;
                }
            }

            $validated['media'] = $media;
            $validated['documents'] = $documents;

            $listing = Listing::create($validated);

            // Create auction record for admin users if ad_type is auction
            if (in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN]) && in_array($validated['ad_type'], ['live_auction', 'scheduled_auction'])) {
                $auction_type = $validated['ad_type'] === 'live_auction' ? 'live' : 'scheduled';

                $auctionData = [
                    'listing_id' => $listing->id,
                    'type' => $auction_type,
                    'start_time' => \Carbon\Carbon::createFromFormat('Y-m-d H:i', $validated['auction_start_date'] . ' ' . $validated['auction_start_time']),
                    'end_time' => $validated['ad_type'] === 'scheduled_auction' ?
                        \Carbon\Carbon::createFromFormat('Y-m-d H:i', $validated['auction_end_date'] . ' ' . $validated['auction_end_time']) : null,
                    'starting_price' => $validated['price'],
                    'status' => 'pending',
                ];

                \App\Models\Auction::create($auctionData);
            }

            return response()->json([
                'message' => 'Listing created successfully',
                'data' => new ListingResource($listing)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ListingController@store validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'files' => $request->file()
            ]);
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ListingController@store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'files' => $request->file()
            ]);
            return response()->json(['message' => 'Failed to create listing'], 500);
        }
    }

    //  عرض إعلان محدد
    public function show(Listing $listing)
    {
        try {
            return new ListingResource($listing);
        } catch (\Exception $e) {
            Log::error('ListingController@show failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch listing details'], 500);
        }
    }

    //  تحديث إعلان
    public function update(Request $request, Listing $listing)
    {
        try {
            if ($listing->seller_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $listing->update($request->all());

            return response()->json([
                'message' => 'Listing updated successfully',
                'data' => new ListingResource($listing)
            ]);
        } catch (\Exception $e) {
            Log::error('ListingController@update failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update listing'], 500);
        }
    }

    //  حذف إعلان
    public function destroy(Listing $listing)
    {
        try {
            if ($listing->seller_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $listing->delete();

            return response()->json(['message' => 'Listing deleted successfully']);
        } catch (\Exception $e) {
            Log::error('ListingController@destroy failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete listing'], 500);
        }
    }

    //  موافقة الأدمن على إعلان
    public function approve(Request $request, Listing $listing)
    {
        try {
            if (!in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can approve listings'], 403);
            }

            $validated = $request->validate([
                'auction_type' => 'nullable|in:scheduled,live',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after:start_time',
                'starting_price' => 'nullable|numeric|min:0',
            ]);

            $listing->update([
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'status' => 'active', // Set status to active when approved
            ]);

            // إنشاء مزاد إذا كان نوع الإعلان مزاد
            if (in_array($listing->ad_type, ['live_auction', 'scheduled_auction'])) {
                $auctionData = [
                    'listing_id' => $listing->id,
                    'type' => $listing->ad_type === 'live_auction' ? 'live' : 'scheduled',
                    'start_time' => \Carbon\Carbon::createFromFormat('Y-m-d H:i', $listing->auction_start_date . ' ' . $listing->auction_start_time),
                    'end_time' => $listing->ad_type === 'scheduled_auction' ?
                        \Carbon\Carbon::createFromFormat('Y-m-d H:i', $listing->auction_end_date . ' ' . $listing->auction_end_time) : null,
                    'starting_price' => $listing->price,
                    'status' => 'pending',
                ];

                \App\Models\Auction::create($auctionData);
            }

            // إرسال إشعار للبائع
            $listing->seller->notify(new \App\Notifications\GeneralNotification(
                'تم الموافقة على الإعلان',
                'تم الموافقة على إعلانك وأصبح متاحًا للعرض.'
            ));

            return response()->json(['message' => 'Listing approved successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ListingController@approve failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to approve listing'], 500);
        }
    }

    //  رفض الأدمن على إعلان
    public function reject(Listing $listing)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return response()->json(['message' => 'Only admin can reject listings'], 403);
            }

            $listing->update([
                'approval_status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'status' => 'inactive',
            ]);

            // إرسال إشعار للبائع
            $listing->seller->notify(new \App\Notifications\GeneralNotification(
                'تم رفض الإعلان',
                'تم رفض إعلانك. يرجى مراجعة الشروط والأحكام.'
            ));

            return response()->json(['message' => 'Listing rejected successfully']);
        } catch (\Exception $e) {
            Log::error('ListingController@reject failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to reject listing'], 500);
        }
    }
}
