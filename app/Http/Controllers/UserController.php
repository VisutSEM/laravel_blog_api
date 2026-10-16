<?php

namespace App\Http\Controllers;

use App\Models\User;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get users list (Sanitized public preview).
     */
    public function show(): JsonResponse
    {
        $users = User::select('id', 'name', 'profile_picture')->get();

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'data'    => $users,
        ], 200);
    }

    /**
     * Get logged-in user's complete profile with addresses.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('addresses');

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'data'    => $user,
        ], 200);
    }

    /**
     * Update logged-in user's profile details.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Profile updated successfully',
            'data'    => $user->fresh(),
        ], 200);
    }

    /**
     * Upload or update profile picture via Cloudinary.
     */
    public function updateProfilePicture(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = $request->user();

        $cloudinaryUrl = config('services.cloudinary.url', env('CLOUDINARY_URL'));
        $cloudinary = new Cloudinary($cloudinaryUrl);

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
            'status'  => 'success',
            'message' => 'Profile picture updated successfully',
            'data'    => $user,
        ], 200);
    }

    /**
     * Delete user's profile picture.
     */
    public function deleteProfilePicture(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->profile_picture) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'No profile picture found.',
            ], 404);
        }

        $user->profile_picture = null;
        $user->save();

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Profile picture deleted successfully.',
            'data'    => $user,
        ], 200);
    }
}