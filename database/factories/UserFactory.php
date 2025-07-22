<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->optional()->phoneNumber(),
            'avatar_url' => fake()->optional()->imageUrl(),
            'organization_id' => null,
            'role_id' => 1, // Default role
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a super admin user.
     */
    public function superAdmin(): static
    {
        return $this->state(function (array $attributes) {
            // Buscar o crear el rol de superadmin
            $superAdminRole = \App\Models\Role::firstOrCreate(
                ['name' => 'superadmin'],
                ['description' => 'Super Administrator']
            );

            return [
                'role_id' => $superAdminRole->id,
                'email' => 'superadmin@example.com',
                'first_name' => 'Super',
                'last_name' => 'Admin'
            ];
        });
    }

    /**
     * Create a regular admin user.
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            $adminRole = \App\Models\Role::firstOrCreate(
                ['name' => 'admin'],
                ['description' => 'Administrator']
            );

            return [
                'role_id' => $adminRole->id,
            ];
        });
    }

    /**
     * Create an organization admin user.
     */
    public function organizationAdmin(): static
    {
        return $this->state(function (array $attributes) {
            $orgAdminRole = \App\Models\Role::firstOrCreate(
                ['name' => 'organization_admin'],
                ['description' => 'Organization Administrator']
            );

            return [
                'role_id' => $orgAdminRole->id,
            ];
        });
    }
}
