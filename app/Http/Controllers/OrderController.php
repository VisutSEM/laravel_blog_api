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
            ->paginate(15);

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
            'payment_method'   => 'nullable|string|in:cash_on_delivery,credit_card,bank_transfer',
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
            if (!$item->product) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'One of the items in your cart is no longer available.',
                ], 422);
            }

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
        try {
            $order = DB::transaction(function () use ($user, $cartItems, $totalAmount, $validated) {
                $order = Order::create([
                    'user_id'          => $user->id,
                    'order_number'     => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                    'total_amount'     => $totalAmount,
                    'status'           => 'pending',
                    'shipping_address' => $validated['shipping_address'],
                    'payment_method'   => $validated['payment_method'] ?? 'cash_on_delivery',
                    'payment_status'   => 'unpaid',
                ]);

                foreach ($cartItems as $item) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item->product_id,
                        'unit_price' => $item->product->price,
                        'quantity'   => $item->quantity,
                    ]);

                    $item->product->decrement('stock', $item->quantity);
                }

                // Clear user's cart
                Cart::where('user_id', $user->id)->delete();

                return $order;
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Order placed successfully.',
                'data'    => $order->load('items.product'),
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to place order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific order with its line items.
     */
    public function show(Request $request, Order|int|string $order): JsonResponse
    {
        $userId = $request->user()->id;
        $orderModel = $order instanceof Order ? $order : Order::where('user_id', $userId)->findOrFail($order);

        if ($orderModel->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $orderModel->load('items.product'),
        ], 200);
    }

    /**
     * Cancel an existing order (if it is still pending) and restore stock.
     */
    public function cancel(Request $request, Order|int|string $order): JsonResponse
    {
        $userId = $request->user()->id;
        $orderModel = $order instanceof Order ? $order : Order::where('user_id', $userId)->findOrFail($order);

        if ($orderModel->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($orderModel->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only pending orders can be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($orderModel) {
            // Restore product stock
            foreach ($orderModel->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $orderModel->update([
                'status' => 'cancelled',
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Order cancelled successfully.',
            'data'    => $orderModel->fresh('items.product'),
        ], 200);
    }

    /**
     * Update order (supports cancel status).
     */
    public function update(Request $request, Order|int|string $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:cancelled',
        ]);

        return $this->cancel($request, $order);
    }

    /**
     * Remove an order record (Only for cancelled orders).
     */
    public function destroy(Request $request, Order|int|string $order): JsonResponse
    {
        $userId = $request->user()->id;
        $orderModel = $order instanceof Order ? $order : Order::where('user_id', $userId)->findOrFail($order);

        if ($orderModel->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($orderModel->status !== 'cancelled') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only cancelled orders can be removed from history.',
            ], 400);
        }

        $orderModel->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Order history deleted.',
        ], 200);
    }
}