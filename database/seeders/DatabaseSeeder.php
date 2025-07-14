<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\TrustLevel;
use App\Models\Organization;
use App\Models\User;
use App\Models\EventCategory;
use App\Models\EventStatus;
use App\Models\Event;
use App\Models\InvitationStatus;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'organization_admin']);
        Role::create(['name' => 'organization_user']);

        TrustLevel::create(['name' => 'verified']);
        TrustLevel::create(['name' => 'unverified']);

        Organization::create([
            'name' => 'Enteturismo',
            'slug' => 'enteturismo',
            'website_url' => 'https://enteturismo.com',
            'address' => '123 Calle Principal',
            'phone' => '123456789',
            'email' => 'contacto@enteturismo.com',
            'trust_level_id' => 1,
            'is_active' => true,
        ]);

        User::create([
            'organization_id' => 1,
            'role_id' => 1,
            'first_name' => 'Marcos',
            'last_name' => 'Rillo',
            'email' => 'marcos@enteturismo.com',
            'password' => bcrypt('password'),
            'phone' => '987654321',
            'is_active' => true,
        ]);

        EventCategory::create(['name' => 'Conference']);
        EventCategory::create(['name' => 'Workshop']);

        EventStatus::create(['name' => 'draft']);
        EventStatus::create(['name' => 'pending']);
        EventStatus::create(['name' => 'approved']);
        EventStatus::create(['name' => 'rejected']);

        Event::create([
            'organization_id' => 1,
            'category_id' => 1,
            'status_id' => 1,
            'title' => 'Conferencia de Turismo',
            'description' => 'Evento anual de turismo.',
            'start_date' => '2025-07-10 14:43:13',
            'end_date' => '2025-07-11 14:43:13',
            'location' => 'Centro de Convenciones',
            'is_public' => false,
            'banner' => 'https://enteturismo.com/event-banner.png',
            'created_by' => 1,
        ]);

        InvitationStatus::create(['name' => 'pending']);
        InvitationStatus::create(['name' => 'accepted']);
        InvitationStatus::create(['name' => 'rejected']);
    }
}
?>