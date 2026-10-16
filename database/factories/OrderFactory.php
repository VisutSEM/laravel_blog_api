<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'order_number'     => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'total_amount'     => fake()->randomFloat(2, 25, 500),
            'status'           => fake()->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'payment_status'   => fake()->randomElement(['unpaid', 'paid']),
            'payment_method'   => fake()->randomElement(['cash_on_delivery', 'credit_card', 'bank_transfer']),
            'shipping_address' => fake()->address(),
        ];
    }
}
