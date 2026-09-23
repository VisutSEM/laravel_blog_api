<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Get all wishlist items for the logged-in user.
     *
     * GET /api/wishlist
     */
    public function index(Request $request)
    {
        $wishlist = $request->user()->wishlist()->get();

        return response()->json([
            'status' => 'success',
            'data'   => $wishlist,
        ]);
    }

    /**
     * Toggle add/remove a product from the user's wishlist.
     *
     * POST /api/wishlist/toggle
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        // toggle() attaches if missing, detaches if present
        $result = $user->wishlist()->toggle($productId);

        $attached = count($result['attached']) > 0;

        return response()->json([
            'status'  => 'success',
            'added'   => $attached,
            'message' => $attached 
                ? 'Product added to wishlist' 
                : 'Product removed from wishlist',
        ]);
    }
}