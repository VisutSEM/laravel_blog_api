<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;

class UserController extends Controller
{
    // Get all users
    public function show()
    {
        $users = User::all();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    // Get logged-in user's profile
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    // Upload profile picture
    public function updateProfilePicture(Request $request)
{
    $request->validate([
        'profile_picture' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
    ]);

    $user = $request->user();

    $cloudinary = new Cloudinary();

    $uploadedFile = $cloudinary
        ->uploadApi()
        ->upload(
            $request->file('profile_picture')->getRealPath(),
            [
                'folder' => 'profile_pictures',
            ]
        );

    $user->profile_picture = $uploadedFile['secure_url'];
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'data' => $user,
    ]);
}

    //Delete profile picture
    public function deleteProfilePicture(Request $request)
{
    $user = $request->user();

    if (!$user->profile_picture) {
        return response()->json([
            'success' => false,
            'message' => 'No profile picture found.',
        ], 404);
    }

    $user->profile_picture = null;
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Profile picture deleted successfully.',
        'data' => $user,
    ]);
}
}