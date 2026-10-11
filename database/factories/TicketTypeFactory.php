<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
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
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['一般', '学生', '小学生以下', '早割']),
            'price' => fake()->numberBetween(5, 50) * 100,
            'capacity' => fake()->numberBetween(10, 200),
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
        ];
    }

    /**
     * Indicate that the ticket type is not on sale yet.
     */
    public function notOnSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_start_at' => now()->addWeek(),
            'sales_end_at' => now()->addMonth(),
        ]);
    }
}
