<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Process checkout for the authenticated user.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address' => 'required|string|max:500',
            'payment_method'   => 'nullable|string|in:cash_on_delivery,credit_card,bank_transfer',
        ]);

        $user = $request->user();

        // 1. Fetch user's cart items with products
        $cartItems = Cart::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cart is empty. Cannot process checkout.',
            ], 400);
        }

        // 2. Validate product existence and stock
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
                    'message' => "Insufficient stock for product: {$item->product->name}. Available: {$item->product->stock}, Requested: {$item->quantity}.",
                ], 422);
            }
        }

        // 3. Calculate total amount
        $totalAmount = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        // 4. Process order creation within DB transaction
        try {
            $order = DB::transaction(function () use ($user, $cartItems, $totalAmount, $validated) {
                $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                $paymentMethod = $validated['payment_method'] ?? 'cash_on_delivery';

                $order = Order::create([
                    'user_id'          => $user->id,
                    'order_number'     => $orderNumber,
                    'total_amount'     => $totalAmount,
                    'status'           => 'pending',
                    'payment_status'   => 'unpaid',
                    'payment_method'   => $paymentMethod,
                    'shipping_address' => $validated['shipping_address'],
                ]);

                foreach ($cartItems as $item) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item->product_id,
                        'quantity'   => $item->quantity,
                        'unit_price' => $item->product->price,
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
                'message' => 'Failed to process checkout: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alias for legacy route calls.
     */
    public function processCheckout(Request $request): JsonResponse
    {
        return $this->checkout($request);
    }
}