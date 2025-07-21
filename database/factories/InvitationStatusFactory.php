<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvitationStatus>
 */
class InvitationStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['pending', 'accepted', 'rejected', 'expired']),
        ];
    }

    /**
     * Indicate that the status is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'pending',
        ]);
    }

    /**
     * Indicate that the status is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'accepted',
        ]);
    }

    /**
     * Indicate that the status is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'rejected',
        ]);
    }

    /**
     * Indicate that the status is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'expired',
        ]);
    }
}
