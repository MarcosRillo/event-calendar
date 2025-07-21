<?php

namespace Database\Factories;

use App\Models\InvitationAdminData;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvitationAdminDataFactory extends Factory
{
    protected $model = InvitationAdminData::class;

    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
