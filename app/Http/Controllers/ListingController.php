<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ListingResource;

class ListingController extends Controller
{
    //  عرض كل الإعلانات (مع فلاتر اختيارية)
    public function index(Request $request)
    {
        try {
            $query = Listing::query()->where('approval_status', 'approved');

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $listings = $query->latest()->paginate(10);

            return ListingResource::collection($listings);
        } catch (\Exception $e) {
            Log::error('ListingController@index failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch listings'], 500);
        }
    }

    //  إنشاء إعلان جديد
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'ad_type' => 'required|in:ad,auction',
                'title' => 'required|string|max:255',
                'category' => 'required|string|max:100',
                'section' => 'nullable|string|max:50',
                'city' => 'required|string|max:100',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'condition' => 'required|string',
                'model' => 'required|string',
                'serial_number' => 'required|string',
                'vehicle_type' => 'nullable|string',
                'fuel_type' => 'nullable|string',
                'transmission' => 'nullable|string',
                'color' => 'nullable|string',
                'media' => 'nullable|array',
                'documents' => 'nullable|array',
                'buy_now' => 'boolean'
            ]);

            $validated['seller_id'] = Auth::id();
            $validated['approval_status'] = Auth::user()->role === 'admin' ? 'approved' : 'pending';
            $validated['status'] = 'active'; // Set status to active for new listings

            $listing = Listing::create($validated);

            return response()->json([
                'message' => 'Listing created successfully',
                'data' => new ListingResource($listing)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ListingController@store failed', ['error' => $e->getMessage()]);
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
    public function approve(Listing $listing)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return response()->json(['message' => 'Only admin can approve listings'], 403);
            }

            $listing->update([
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'status' => 'active', // Set status to active when approved
            ]);

            return response()->json(['message' => 'Listing approved successfully']);
        } catch (\Exception $e) {
            Log::error('ListingController@approve failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to approve listing'], 500);
        }
    }
}
