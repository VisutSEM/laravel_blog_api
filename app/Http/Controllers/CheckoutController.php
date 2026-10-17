<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Process checkout for the authenticated user, handling Cash on Delivery (COD)
     * and digital payment methods.
     */
    public function checkout(Request $request): JsonResponse
    {
        // Default payment_method to cash_on_delivery if omitted for backwards compatibility
        if (!$request->has('payment_method')) {
            $request->merge(['payment_method' => 'cash_on_delivery']);
        }

        // A. Request Validation
        $request->validate([
            'shipping_address' => 'required|string|max:500',
            'payment_method'   => 'required|string|in:cash_on_delivery,aba_pay,card,credit_card,bank_transfer',
            'shipping_fee'     => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string|max:500',
            'address_id'       => 'nullable|integer',
        ]);

        $user = $request->user();

        // 1. Fetch user's cart items with associated products
        $cartItems = Cart::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Your cart is empty',
            ], 400);
        }

        // Validate product availability and inventory stock
        foreach ($cartItems as $item) {
            if (!$item->product) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'One of the items in your cart is no longer available.',
                ], 422);
            }

            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => "Insufficient stock for product: {$item->product->name}. Available: {$item->product->stock}, Requested: {$item->quantity}.",
                ], 422);
            }
        }

        // 2. Calculate totals
        $subtotal = $cartItems->sum(function ($item) {
            return (float) $item->product->price * $item->quantity;
        });

        $shippingFee = (float) $request->input('shipping_fee', 0.0);
        $totalAmount = $subtotal + $shippingFee;

        // B. Order Creation Attributes for COD vs Digital Payments
        $paymentMethod = $request->input('payment_method', 'cash_on_delivery');
        $paymentStatus = $paymentMethod === 'cash_on_delivery' ? 'unpaid' : 'paid';

        DB::beginTransaction();
        try {
            // 3. Create the Order
            $order = Order::create([
                'user_id'          => $user->id,
                'order_number'     => 'ORD-' . strtoupper(uniqid()),
                'total_amount'     => $totalAmount,
                'shipping_fee'     => $shippingFee,
                'shipping_address' => $request->shipping_address,
                'payment_method'   => $paymentMethod,
                'payment_status'   => $paymentStatus,
                'status'           => 'pending',
                'notes'            => $request->notes,
                'address_id'       => $request->address_id,
            ]);

            // 4. Create Order Items & decrement product inventory
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name,
                    'price'        => $item->product->price,
                    'unit_price'   => $item->product->price,
                    'quantity'     => $item->quantity,
                    'image'        => $item->product->image,
                ]);

                // Decrement stock
                $item->product->decrement('stock', $item->quantity);
            }

            // 5. Clear the user's cart
            Cart::where('user_id', $user->id)->delete();

            DB::commit();

            $orderWithItems = $order->load(['items.product']);

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => $paymentMethod === 'cash_on_delivery'
                    ? 'Order placed successfully with Cash on Delivery!'
                    : 'Order placed successfully!',
                'order'   => $orderWithItems,
                'data'    => $orderWithItems,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Checkout failed: ' . $e->getMessage(),
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