<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(9, 11),
            'company_id' => fake()->numberBetween(2, 4),
            'status' => fake()->numberBetween(0, 3),
            'type_id' => fake()->numberBetween(1, 10),
            'description' => fake()->sentence(),
            'duration' => 0,
        ];
    }
}
