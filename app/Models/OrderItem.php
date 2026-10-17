<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'unit_price',
        'quantity',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'price'      => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function getPriceAttribute($value)
    {
        return $value ?? $this->unit_price;
    }

    public function getUnitPriceAttribute($value)
    {
        return $value ?? $this->price;
    }

    /**
     * Relationship: OrderItem belongs to an Order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relationship: OrderItem belongs to a Product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}