<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TicketTypeCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'name' => fake()->sentence(5),
            'ticket_type_category_id' => TicketTypeCategory::factory(),
            'default_sla_take' => 120,
            'default_sla_solve' => 3000,
            'company_id' => Company::factory(),
            'brand_id' => 1,
        ];
    }
}
