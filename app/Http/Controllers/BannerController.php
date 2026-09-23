<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banners = Banner::all();

        return response()->json([
            'success' => true,
            'data' => $banners
        ], 200);
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

        // Upload image directly using Cloudinary facade
        $uploadedFile = Cloudinary::upload(
            $request->file('image')->getRealPath(),
            ['folder' => 'banners']
        );

        $banner = Banner::create([
            'title'     => $request->title,
            'image_url' => $uploadedFile->getSecurePath(),
            'public_id' => $uploadedFile->getPublicId(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully.',
            'data'    => [
                'id'        => $banner->id,
                'title'     => $banner->title,
                'image_url' => $banner->image_url,
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
            'data'    => $banner,
        ], 200);
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

            // Delete old image from Cloudinary if public_id exists
            if ($banner->public_id) {
                Cloudinary::destroy($banner->public_id);
            }

            // Upload new image to Cloudinary
            $uploadedFile = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'banners']
            );

            $banner->image_url = $uploadedFile->getSecurePath();
            $banner->public_id = $uploadedFile->getPublicId();
        }

        $banner->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => $banner->id,
                'title'     => $banner->title,
                'image_url' => $banner->image_url,
            ]
        ], 200);
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

        // Delete image from Cloudinary
        if ($banner->public_id) {
            Cloudinary::destroy($banner->public_id);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully.'
        ], 200);
    }
}