<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;

class BannerController extends Controller
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        // Automatically reads CLOUDINARY_URL from your .env file
        $this->cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banners = Banner::all();

        return response()->json([
            'success' => true,
            'data'    => $banners
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:255',
            'image_url' => 'required_without:image|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'image'     => 'required_without:image_url|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Support either 'image_url' or 'image' from form-data input
        $file = $request->file('image_url') ?? $request->file('image');

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'No image file uploaded.'
            ], 422);
        }

        // Upload image using official Cloudinary SDK
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            ['folder' => 'banners']
        );

        $banner = Banner::create([
            'title'     => $request->title,
            'image_url' => $result['secure_url'],
            'public_id' => $result['public_id'],
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
            'title'     => 'required|string|max:255',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $banner->title = $request->title;

        $file = $request->file('image_url') ?? $request->file('image');

        if ($file) {
            // Delete old image from Cloudinary if public_id exists
            if ($banner->public_id) {
                $this->cloudinary->uploadApi()->destroy($banner->public_id);
            }

            // Upload new image
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                ['folder' => 'banners']
            );

            $banner->image_url = $result['secure_url'];
            $banner->public_id = $result['public_id'];
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
            $this->cloudinary->uploadApi()->destroy($banner->public_id);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully.'
        ], 200);
    }
}