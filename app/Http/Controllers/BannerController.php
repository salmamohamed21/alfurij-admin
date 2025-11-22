<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::ordered()->get()->map(function ($banner) {
            $banner->image_path = 'http://localhost:8000' . Storage::url(str_replace('\\', '/', $banner->image_path));
            return $banner;
        });
        return response()->json($banners, 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'title' => $request->title,
            'image_path' => $imagePath,
            'order' => Banner::max('order') + 1,
        ]);

        $banner->image_path = 'http://localhost:8000' . Storage::url(str_replace('\\', '/', $banner->image_path));

        return response()->json($banner, 201);
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
        ]);

        $banner->update($request->only(['title']));
        return response()->json($banner);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:banners,id',
        ]);

        foreach ($request->order as $index => $id) {
            Banner::where('id', $id)->update(['order' => $index + 1]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    public function destroy(Banner $banner)
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return response()->json(['message' => 'Banner deleted successfully']);
    }
}
