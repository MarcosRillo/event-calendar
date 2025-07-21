<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\InvitationStatus;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
            'status_id' => 1, // Default status
            'corrections_notes' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_by' => 1, // Default user
            'updated_by' => null,
            'organization_id' => null,
            'rejected_reason' => null,
        ];
    }

    /**
     * Indicate that the invitation is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'rejected_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the invitation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
            'rejected_reason' => null,
        ]);
    }
}
