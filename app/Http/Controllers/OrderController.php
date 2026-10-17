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

    /**
     * Admin: List all orders across all users with optional filters.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with(['items.product', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('shipping_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $orders = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'data'    => $orders,
        ], 200);
    }

    /**
     * Admin: Show details of any order.
     */
    public function adminShow(Request $request, int|string $id): JsonResponse
    {
        $order = Order::with(['items.product', 'user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'order'   => $order,
            'data'    => $order,
        ], 200);
    }

    /**
     * Admin: Update order status, payment status, or notes.
     */
    public function adminUpdateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status'         => 'required|string|in:pending,processing,shipped,delivered,completed,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,paid,refunded',
            'notes'          => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }
                $order->update(['status' => 'cancelled']);
            });

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Order cancelled and stock restored.',
                'order'   => $order->fresh(['items.product', 'user']),
                'data'    => $order->fresh(['items.product', 'user']),
            ], 200);
        }

        $newStatus = $validated['status'];
        $newPaymentStatus = $validated['payment_status'] ?? $order->payment_status;

        // COD Order Lifecycle:
        // When admin/rider marks delivered or completed, auto-mark payment_status as 'paid'
        if (in_array($newStatus, ['delivered', 'completed']) && $order->payment_method === 'cash_on_delivery') {
            $newPaymentStatus = 'paid';
        }

        $order->update([
            'status'         => $newStatus,
            'payment_status' => $newPaymentStatus,
            'notes'          => $validated['notes'] ?? $order->notes,
        ]);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => "Order status updated to '{$newStatus}'.",
            'order'   => $order->fresh(['items.product', 'user']),
            'data'    => $order->fresh(['items.product', 'user']),
        ], 200);
    }

    /**
     * Admin or Delivery Rider: Approve that an order has been successfully delivered.
     * Automatically sets status to 'delivered' and for COD sets payment_status to 'paid'.
     */
    public function approveDelivery(Request $request, int|string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Cannot approve delivery for a cancelled order.',
            ], 422);
        }

        $updateData = [
            'status' => 'delivered',
        ];

        // For COD orders: Approving delivery confirms physical cash collection
        if ($order->payment_method === 'cash_on_delivery') {
            $updateData['payment_status'] = 'paid';
        }

        if ($request->filled('notes')) {
            $updateData['notes'] = $request->notes;
        }

        $order->update($updateData);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Order delivery approved successfully. Payment marked as paid.',
            'order'   => $order->fresh(['items.product', 'user']),
            'data'    => $order->fresh(['items.product', 'user']),
        ], 200);
    }
}