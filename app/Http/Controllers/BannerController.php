<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banner =  Banner::get()->all();
        return response()->json([
            'message' => 'success',
            'data' => $banner
        ],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Upload image
        $path = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'title' => $request->title,
            'image_url' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully.',
            'data' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image_url' => asset('storage/' . $banner->image_url),
            ]
        ], 201);

        
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
         $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $banner,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
{
    $banner = Banner::findOrFail($id);

    $request->validate([
        'title' => 'required|string|max:255',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $banner->title = $request->title;

    if ($request->hasFile('image')) {

        // Delete old image
        if ($banner->image_url) {
            Storage::disk('public')->delete($banner->image_url);
        }

        // Upload new image
        $banner->image_url = $request->file('image')->store('banners', 'public');
    }

    $banner->save();

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $banner->id,
            'title' => $banner->title,
            'image_url' => asset('storage/' . $banner->image_url),
        ]
    ]);
}
   

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $banner = Banner::find($id);

    if (!$banner) {
        return response()->json([
            'success' => false,
            'message' => 'Banner not found.'
        ], 404);
    }

    // Delete image from storage
    if ($banner->image_url && Storage::disk('public')->exists($banner->image_url)) {
        Storage::disk('public')->delete($banner->image_url);
    }

    $banner->delete();

    return response()->json([
        'success' => true,
        'message' => 'Banner deleted successfully.'
    ]);
    
    }
}
