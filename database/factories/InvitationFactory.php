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
        return $this->state(function (array $attributes) {
            $pendingStatus = \App\Models\InvitationStatus::firstOrCreate(
                ['name' => 'pending'],
                ['description' => 'Pending review']
            );

            return [
                'status_id' => $pendingStatus->id,
                'accepted_at' => null,
                'rejected_reason' => null,
            ];
        });
    }

    /**
     * Indicate that the invitation is approved.
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            $approvedStatus = \App\Models\InvitationStatus::firstOrCreate(
                ['name' => 'approved'],
                ['description' => 'Approved']
            );

            return [
                'status_id' => $approvedStatus->id,
                'accepted_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the invitation needs corrections.
     */
    public function correctionsNeeded(): static
    {
        return $this->state(function (array $attributes) {
            $correctionsStatus = \App\Models\InvitationStatus::firstOrCreate(
                ['name' => 'corrections_needed'],
                ['description' => 'Corrections needed']
            );

            return [
                'status_id' => $correctionsStatus->id,
                'corrections_notes' => fake()->paragraph(),
            ];
        });
    }

    /**
     * Indicate that the invitation is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Create invitation with organization data.
     */
    public function withOrganizationData(): static
    {
        return $this->afterCreating(function (\App\Models\Invitation $invitation) {
            \App\Models\InvitationOrganizationData::factory()->create([
                'invitation_id' => $invitation->id,
            ]);
        });
    }

    /**
     * Create invitation with admin data.
     */
    public function withAdminData(): static
    {
        return $this->afterCreating(function (\App\Models\Invitation $invitation) {
            \App\Models\InvitationAdminData::factory()->create([
                'invitation_id' => $invitation->id,
            ]);
        });
    }

    /**
     * Create complete invitation with all related data.
     */
    public function complete(): static
    {
        return $this->withOrganizationData()->withAdminData();
    }
}
