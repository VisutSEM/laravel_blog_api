<?php

namespace App\Http\Controllers;

use App\Http\Requests\Address\StoreAddressRequest;
use App\Models\Adress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of addresses.
     * If user is authenticated, returns user's addresses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Adress::query();

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $addresses = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $addresses,
        ], 200);
    }

    /**
     * Store a newly created address in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'   => 'nullable|exists:users,id',
            'city'      => 'required|string|max:100',
            'street'    => 'required|string|max:255',
            'state'     => 'required|string|max:100',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if (empty($data['user_id']) && $request->user()) {
            $data['user_id'] = $request->user()->id;
        }

        if (empty($data['user_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'The user_id field is required when unauthenticated.',
            ], 422);
        }

        $address = Adress::create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Address created successfully',
            'data'    => $address,
        ], 201);
    }

    /**
     * Display the specified address.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $address = Adress::findOrFail($id);

        if ($request->user() && $address->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $address,
        ], 200);
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $address = Adress::findOrFail($id);

        if ($request->user() && $address->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'city'      => 'sometimes|required|string|max:100',
            'street'    => 'sometimes|required|string|max:255',
            'state'     => 'sometimes|required|string|max:100',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $address->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Address updated successfully',
            'data'    => $address,
        ], 200);
    }

    /**
     * Remove the specified address.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $address = Adress::findOrFail($id);

        if ($request->user() && $address->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $address->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Address deleted successfully',
        ], 200);
    }
}
