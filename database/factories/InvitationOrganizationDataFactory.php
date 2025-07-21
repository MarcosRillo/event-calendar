<?php

namespace Database\Factories;

use App\Models\InvitationOrganizationData;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvitationOrganizationDataFactory extends Factory
{
    protected $model = InvitationOrganizationData::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        
        return [
            'invitation_id' => Invitation::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'website_url' => $this->faker->url(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
        ];
    }
}
