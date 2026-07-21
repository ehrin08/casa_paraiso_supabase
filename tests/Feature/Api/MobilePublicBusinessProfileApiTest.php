<?php

namespace Tests\Feature\Api;

use App\Models\ApplicationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilePublicBusinessProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_profile_exposes_only_approved_contact_fields_without_authentication(): void
    {
        ApplicationSetting::factory()->create([
            'business_name' => 'Casa Paraiso Body & Wellness Spa',
            'business_address' => 'Barangay Cuta East, Santa Teresita, Batangas, Philippines',
            'location_landmarks' => 'In front of Alfamart and PLDT; in the same building as BDO Network Bank.',
            'contact_email' => null,
            'contact_phone' => null,
            'facebook_url' => 'https://www.facebook.com/61579320037378',
            'messenger_url' => 'https://m.me/61579320037378',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso',
        ]);

        $this->getJson('/api/v1/public/business-profile')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertExactJson([
                'data' => [
                    'business_name' => 'Casa Paraiso Body & Wellness Spa',
                    'business_address' => 'Barangay Cuta East, Santa Teresita, Batangas, Philippines',
                    'location_landmarks' => 'In front of Alfamart and PLDT; in the same building as BDO Network Bank.',
                    'contact_email' => null,
                    'contact_phone' => null,
                    'facebook_url' => 'https://www.facebook.com/61579320037378',
                    'messenger_url' => 'https://m.me/61579320037378',
                    'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso',
                ],
            ]);
    }
}
