<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    /**
     * Display the authenticated user's cart items.
     */
    public function index(Request $request): JsonResponse
    {
        $cartItems = Cart::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        $totalAmount = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $cartItems,
                'total_amount' => $totalAmount,
            ]
        ], 200);
    }

    /**
     * Store or update an item in the user's cart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $userId = $request->user()->id;

        // Check stock availability
        $product = Product::findOrFail($validated['product_id']);
        if ($product->stock < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Requested quantity exceeds available stock.'
            ], 422);
        }

        // Increment quantity if item exists, otherwise create new entry
        $cartItem = Cart::where('user_id', $userId)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id'    => $userId,
                'product_id' => $validated['product_id'],
                'quantity'   => $validated['quantity'],
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Product added to cart successfully.',
            'data'    => $cartItem->load('product'),
        ], 201);
    }

    /**
     * Display a specific cart item.
     */
    public function show(Request $request, Cart $cart): JsonResponse
    {
        // Authorize item ownership
        if ($cart->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $cart->load('product'),
        ], 200);
    }

    /**
     * Update the quantity of a specific cart item.
     */
    public function update(Request $request, Cart $cart): JsonResponse
    {
        if ($cart->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Check stock availability
        if ($cart->product->stock < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Requested quantity exceeds available stock.'
            ], 422);
        }

        $cart->update([
            'quantity' => $validated['quantity'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cart updated successfully.',
            'data'    => $cart->load('product'),
        ], 200);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(Request $request, Cart $cart): JsonResponse
    {
        if ($cart->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $cart->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Item removed from cart.',
        ], 200);
    }
}