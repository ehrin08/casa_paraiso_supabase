<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_staff_management(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $staffProfile = StaffProfile::factory()->create();

        foreach ([
            ['GET', route('admin.staff.index', absolute: false)],
            ['GET', route('admin.staff.create', absolute: false)],
            ['GET', route('admin.staff.show', $staffProfile, false)],
            ['GET', route('admin.staff.edit', $staffProfile, false)],
            ['POST', route('admin.staff.store', absolute: false)],
            ['PATCH', route('admin.staff.update', $staffProfile, false)],
        ] as [$method, $uri]) {
            $this->actingAs($admin)->call($method, $uri)->assertStatus($method === 'POST' || $method === 'PATCH' ? 302 : 200);
            $this->actingAs($staff)->call($method, $uri)->assertForbidden();
            $this->actingAs($customer)->call($method, $uri)->assertForbidden();
        }
    }

    public function test_admin_can_create_staff_with_profile_and_service_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $services = Service::factory()->count(2)->create();

        $response = $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Maria Santos',
            'email' => 'maria.santos@casaparaiso.test',
            'phone' => '09170001234',
            'password' => 'temporary-password',
            'is_active' => '1',
            'position' => 'Spa Therapist',
            'specialization' => 'Hilot massage',
            'bio' => 'Handles massage and relaxation services.',
            'hire_date' => now()->subMonth()->toDateString(),
            'is_bookable' => '1',
            'service_ids' => $services->pluck('id')->all(),
        ]);

        $staffUser = User::query()->where('email', 'maria.santos@casaparaiso.test')->firstOrFail();
        $staffProfile = $staffUser->staffProfile;

        $response->assertRedirect(route('admin.staff.show', $staffProfile, absolute: false));
        $this->assertTrue(Hash::check('temporary-password', $staffUser->password));
        $this->assertDatabaseHas('users', [
            'id' => $staffUser->id,
            'name' => 'Maria Santos',
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('staff_profiles', [
            'id' => $staffProfile->id,
            'user_id' => $staffUser->id,
            'position' => 'Spa Therapist',
            'specialization' => 'Hilot massage',
            'is_bookable' => true,
        ]);

        foreach ($services as $service) {
            $this->assertDatabaseHas('staff_services', [
                'staff_profile_id' => $staffProfile->id,
                'service_id' => $service->id,
            ]);
        }
    }

    public function test_admin_can_update_staff_profile_and_service_assignments_without_changing_password(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create(['password' => 'original-password']);
        $staffProfile = StaffProfile::factory()->for($staffUser)->create([
            'position' => 'Therapist',
            'is_bookable' => true,
        ]);
        $oldService = Service::factory()->create();
        $newService = Service::factory()->create();
        $staffProfile->services()->attach($oldService);
        $originalPasswordHash = $staffUser->password;

        $this->actingAs($admin)->patch(route('admin.staff.update', $staffProfile, false), [
            'name' => 'Updated Staff',
            'email' => 'updated.staff@casaparaiso.test',
            'phone' => '09175550000',
            'password' => '',
            'is_active' => '1',
            'position' => 'Senior Therapist',
            'specialization' => 'Body treatments',
            'bio' => 'Updated staff profile.',
            'hire_date' => now()->subWeek()->toDateString(),
            'is_bookable' => '0',
            'service_ids' => [$newService->id],
        ])->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $staffUser->refresh();
        $staffProfile->refresh();

        $this->assertSame($originalPasswordHash, $staffUser->password);
        $this->assertSame('Updated Staff', $staffUser->name);
        $this->assertSame('updated.staff@casaparaiso.test', $staffUser->email);
        $this->assertSame('Senior Therapist', $staffProfile->position);
        $this->assertFalse($staffProfile->is_bookable);
        $this->assertDatabaseMissing('staff_services', [
            'staff_profile_id' => $staffProfile->id,
            'service_id' => $oldService->id,
        ]);
        $this->assertDatabaseHas('staff_services', [
            'staff_profile_id' => $staffProfile->id,
            'service_id' => $newService->id,
        ]);
    }

    public function test_admin_can_update_staff_password_when_provided(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create(['password' => 'original-password']);
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();

        $this->actingAs($admin)->patch(route('admin.staff.update', $staffProfile, false), [
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'phone' => $staffUser->phone,
            'password' => 'new-password',
            'is_active' => '1',
            'position' => $staffProfile->position,
            'specialization' => $staffProfile->specialization,
            'bio' => $staffProfile->bio,
            'hire_date' => $staffProfile->hire_date?->toDateString(),
            'is_bookable' => '1',
            'service_ids' => [],
        ])->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $this->assertTrue(Hash::check('new-password', $staffUser->fresh()->password));
    }

    public function test_inactive_staff_created_by_admin_cannot_authenticate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Inactive Staff',
            'email' => 'inactive.staff@casaparaiso.test',
            'phone' => null,
            'password' => 'temporary-password',
            'is_active' => '0',
            'position' => 'Reception Staff',
            'specialization' => null,
            'bio' => null,
            'hire_date' => null,
            'is_bookable' => '0',
            'service_ids' => [],
        ])->assertRedirect();

        auth()->logout();

        $response = $this->post('/login', [
            'email' => 'inactive.staff@casaparaiso.test',
            'password' => 'temporary-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_staff_validation_errors_are_returned(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/staff/create')
            ->post('/admin/staff', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'hire_date' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect('/admin/staff/create')
            ->assertSessionHasErrors(['name', 'email', 'password', 'hire_date']);
    }

    public function test_staff_pages_render_profile_and_assignment_information(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create(['name' => 'Rendered Staff']);
        $staffProfile = StaffProfile::factory()->for($staffUser)->create([
            'position' => 'Senior Therapist',
            'specialization' => 'Ventosa therapy',
        ]);
        $service = Service::factory()->create(['name' => 'Ventosa Therapy']);
        $staffProfile->services()->attach($service);

        $this->actingAs($admin)
            ->get('/admin/staff')
            ->assertOk()
            ->assertSee('Rendered Staff')
            ->assertSee('Senior Therapist')
            ->assertSee('Ventosa Therapy');

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $staffProfile, false))
            ->assertOk()
            ->assertSee('Rendered Staff')
            ->assertSee('Ventosa therapy')
            ->assertSee('Ventosa Therapy')
            ->assertSee('Ready for schedules');
    }
}
