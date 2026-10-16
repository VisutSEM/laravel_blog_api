<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return $item->product ? ($item->quantity * $item->product->price) : 0;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $cartItems,
                'total_amount' => round($totalAmount, 2),
            ],
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
        $product = Product::findOrFail($validated['product_id']);

        $cartItem = Cart::where('user_id', $userId)
            ->where('product_id', $validated['product_id'])
            ->first();

        $newQuantity = $cartItem ? ($cartItem->quantity + $validated['quantity']) : $validated['quantity'];

        // Check stock availability for cumulative quantity
        if ($product->stock < $newQuantity) {
            return response()->json([
                'status'  => 'error',
                'message' => "Requested quantity ({$newQuantity}) exceeds available stock ({$product->stock}).",
            ], 422);
        }

        if ($cartItem) {
            $cartItem->quantity = $newQuantity;
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
     * Alias for store method to support legacy route names.
     */
    public function addToCart(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Display a specific cart item.
     */
    public function show(Request $request, Cart|int|string $cart): JsonResponse
    {
        $userId = $request->user()->id;
        $cartItem = $cart instanceof Cart ? $cart : Cart::where('user_id', $userId)->findOrFail($cart);

        if ($cartItem->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $cartItem->load('product'),
        ], 200);
    }

    /**
     * Update the quantity of a specific cart item.
     */
    public function update(Request $request, Cart|int|string $cart): JsonResponse
    {
        $userId = $request->user()->id;
        $cartItem = $cart instanceof Cart ? $cart : Cart::where('user_id', $userId)->findOrFail($cart);

        if ($cartItem->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem->loadMissing('product');
        if ($cartItem->product && $cartItem->product->stock < $validated['quantity']) {
            return response()->json([
                'status'  => 'error',
                'message' => "Requested quantity exceeds available stock ({$cartItem->product->stock}).",
            ], 422);
        }

        $cartItem->update([
            'quantity' => $validated['quantity'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cart updated successfully.',
            'data'    => $cartItem->load('product'),
        ], 200);
    }

    /**
     * Alias for update method to ensure seamless compatibility with PUT /cart/{id} route.
     */
    public function updateQuantity(Request $request, Cart|int|string $cart): JsonResponse
    {
        return $this->update($request, $cart);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(Request $request, Cart|int|string $cart): JsonResponse
    {
        $userId = $request->user()->id;
        $cartItem = $cart instanceof Cart ? $cart : Cart::where('user_id', $userId)->findOrFail($cart);

        if ($cartItem->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $cartItem->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Item removed from cart.',
        ], 200);
    }

    /**
     * Clear all items from the authenticated user's cart.
     */
    public function clear(Request $request): JsonResponse
    {
        Cart::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Cart cleared successfully.',
        ], 200);
    }
}