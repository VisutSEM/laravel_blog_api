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
        return app(CheckoutController::class)->checkout($request);
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
     * Update order status or details (Supports full COD lifecycle and cancellation).
     */
    public function update(Request $request, Order|int|string $order): JsonResponse
    {
        $validated = $request->validate([
            'status'         => 'nullable|string|in:pending,processing,shipped,delivered,completed,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,paid,refunded',
            'notes'          => 'nullable|string|max:500',
        ]);

        $userId = $request->user()->id;
        $orderModel = $order instanceof Order ? $order : Order::where('user_id', $userId)->findOrFail($order);

        if ($orderModel->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        // If cancelling, reuse cancel logic which safely restores stock
        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            return $this->cancel($request, $orderModel);
        }

        $newStatus = $validated['status'] ?? $orderModel->status;
        $newPaymentStatus = $validated['payment_status'] ?? $orderModel->payment_status;

        // COD Order Lifecycle:
        // When rider/store marks status as 'delivered' or 'completed',
        // if payment_method is COD, payment_status automatically transitions to 'paid'.
        if (in_array($newStatus, ['delivered', 'completed']) && $orderModel->payment_method === 'cash_on_delivery') {
            $newPaymentStatus = 'paid';
        }

        $orderModel->update([
            'status'         => $newStatus,
            'payment_status' => $newPaymentStatus,
            'notes'          => $validated['notes'] ?? $orderModel->notes,
        ]);

        $orderModel->load(['items.product']);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Order updated successfully.',
            'order'   => $orderModel,
            'data'    => $orderModel,
        ], 200);
    }

    /**
     * Update order status directly.
     */
    public function updateStatus(Request $request, Order|int|string $order): JsonResponse
    {
        return $this->update($request, $order);
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