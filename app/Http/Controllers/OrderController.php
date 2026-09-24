<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of the user's orders with items.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $orders,
        ], 200);
    }

    /**
     * Checkout: Convert active cart items into an Order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address' => 'required|string|max:500',
            'payment_method'   => 'required|string|in:cash_on_delivery,credit_card,bank_transfer',
        ]);

        $user = $request->user();

        // Fetch user's cart items with products
        $cartItems = Cart::with('product')
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your cart is empty.',
            ], 400);
        }

        // Validate stock before placing order
        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Insufficient stock for product: {$item->product->name}",
                ], 422);
            }
        }

        // Calculate total amount
        $totalAmount = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        // Wrap order creation and inventory updates in a DB transaction
        $order = DB::transaction(function () use ($user, $cartItems, $totalAmount, $validated) {
            $order = Order::create([
                'user_id'          => $user->id,
                'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
                'total_amount'     => $totalAmount,
                'status'           => 'pending',
                'shipping_address' => $validated['shipping_address'],
                'payment_method'   => $validated['payment_method'],
                'payment_status'   => 'unpaid',
            ]);

            foreach ($cartItems as $item) {
                // Create order item snapshot
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item->product_id,
                    'price'      => $item->product->price,
                    'quantity'   => $item->quantity,
                ]);

                // Deduct stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Clear the cart
            Cart::where('user_id', $user->id)->delete();

            return $order;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Order placed successfully.',
            'data'    => $order->load('items.product'),
        ], 201);
    }

    /**
     * Display a specific order with its line items.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $order->load('items.product'),
        ], 200);
    }

    /**
     * Cancel an existing order (if it is still pending).
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:cancelled',
        ]);

        if ($order->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only pending orders can be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            // Restore product stock
            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }

            $order->update([
                'status' => 'cancelled',
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Order cancelled successfully.',
            'data'    => $order->fresh('items.product'),
        ], 200);
    }

    /**
     * Remove an order record (Admin functionality / Soft delete).
     */
    public function destroy(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'cancelled') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only cancelled orders can be removed from history.',
            ], 400);
        }

        $order->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Order history deleted.',
        ], 200);
    }
}