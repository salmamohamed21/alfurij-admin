<?php

namespace App\Http\Controllers;

use App\Models\ModelTruck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ModelController extends Controller
{
    public function index()
    {
        try {
            // Check if user is admin
            if (!in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can view models'], 403);
            }

            $models = ModelTruck::orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Models retrieved successfully',
                'data' => $models
            ]);
        } catch (\Exception $e) {
            Log::error('ModelController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to retrieve models'], 500);
        }
    }

    public function publicIndex()
    {
        try {
            $models = ModelTruck::orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Models retrieved successfully',
                'data' => $models
            ]);
        } catch (\Exception $e) {
            Log::error('ModelController@publicIndex failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to retrieve models'], 500);
        }
    }

    public function addModel(Request $request)
    {
        try {
            // Check if user is admin
            if (!in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can add models'], 403);
            }

            $validated = $request->validate([
                'truckName' => 'required|string|max:255',
                'model' => 'required|string|max:255',
                'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            $imagePath = null;

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('models/images', 'public');
            }

            $model = ModelTruck::create([
                'truck_name' => $validated['truckName'],
                'model_name' => $validated['model'],
                'image_path' => $imagePath,
            ]);

            return response()->json([
                'message' => 'Model added successfully',
                'data' => $model
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ModelController@addModel validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'files' => $request->file()
            ]);
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ModelController@addModel failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'files' => $request->file()
            ]);
            return response()->json(['message' => 'Failed to add model'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Check if user is admin
            if (!in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can update models'], 403);
            }

            $model = ModelTruck::findOrFail($id);

            $validated = $request->validate([
                'truckName' => 'required|string|max:255',
                'model' => 'required|string|max:255',
                'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            $imagePath = $model->image_path;

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($model->image_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($model->image_path);
                }
                $image = $request->file('image');
                $imagePath = $image->store('models/images', 'public');
            }

            $model->update([
                'truck_name' => $validated['truckName'],
                'model_name' => $validated['model'],
                'image_path' => $imagePath,
            ]);

            return response()->json([
                'message' => 'Model updated successfully',
                'data' => $model
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Model not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ModelController@update validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'files' => $request->file()
            ]);
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ModelController@update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'files' => $request->file()
            ]);
            return response()->json(['message' => 'Failed to update model'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Check if user is admin
            if (!in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN])) {
                return response()->json(['message' => 'Only admin can delete models'], 403);
            }

            $model = ModelTruck::findOrFail($id);

            // Delete image if exists
            if ($model->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($model->image_path);
            }

            $model->delete();

            return response()->json([
                'message' => 'Model deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Model not found'], 404);
        } catch (\Exception $e) {
            Log::error('ModelController@destroy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to delete model'], 500);
        }
    }
}
