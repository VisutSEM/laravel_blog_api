<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',     // Added to allow mass assignment from OrderController
        'total_amount',
        'status',
        'payment_status',   // Added to track unpaid/paid status
        'payment_method',
        'shipping_address',
    ];

    /**
     * Relationship: Order belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Order has many OrderItems.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}