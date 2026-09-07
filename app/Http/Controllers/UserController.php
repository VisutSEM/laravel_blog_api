<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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

    // Upload profile picture
    public function updateProfilePicture(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Get logged-in user
        $user = $request->user();

        // Upload image to Cloudinary
        $uploadedFile = cloudinary()->upload(
            $request->file('profile_picture')->getRealPath(),
            [
                'folder' => 'profile_pictures',
            ]
        );

        // Get Cloudinary URL
        $imageUrl = $uploadedFile->getSecurePath();

        // Save URL in database
        $user->profile_picture = $imageUrl;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => $user,
        ]);
    }
}