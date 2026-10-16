<?php

namespace Database\Factories;

use App\Models\Adress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adress>
 */
class AdressFactory extends Factory
{
    protected $model = Adress::class;

    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'street'    => fake()->streetAddress(),
            'city'      => fake()->city(),
            'state'     => fake()->state(),
            'latitude'  => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
