<?php

namespace Tests\Feature;

use App\Models\Camera;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_camera_catalog_requires_sign_in(): void
    {
        $this->getJson('/api/cameras')->assertUnauthorized();
        $this->getJson('/api/rentals')->assertUnauthorized();
        $this->getJson('/api/maintenances')->assertUnauthorized();
    }

    public function test_customer_can_book_a_camera_and_cancel_the_pending_booking(): void
    {
        $customer = $this->createUser('customer');
        $camera = $this->createCamera();
        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/rentals', [
            'camera_id' => $camera->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('rental.status', 'pending')
            ->assertJsonPath('rental.total', 50);

        $rentalId = $response->json('rental.id');

        $this->withToken($token)
            ->getJson('/api/rentals')
            ->assertOk()
            ->assertJsonPath('rentals.0.camera_name', 'Canon EOS 80D');

        $this->withToken($token)
            ->deleteJson("/api/rentals/{$rentalId}")
            ->assertOk();

        $this->assertDatabaseHas('rentals', [
            'id' => $rentalId,
            'status' => 'cancelled',
        ]);
    }

    public function test_camera_cannot_be_booked_twice_for_overlapping_dates(): void
    {
        $customer = $this->createUser('customer');
        $camera = $this->createCamera();
        Rental::create([
            'user_id' => $customer->id,
            'camera_id' => $camera->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'confirmed',
            'total' => 75,
        ]);

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/rentals', [
                'camera_id' => $camera->id,
                'start_date' => now()->addDays(4)->toDateString(),
                'end_date' => now()->addDays(6)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_only_staff_can_manage_maintenance_records(): void
    {
        $customer = $this->createUser('customer');
        $staff = $this->createUser('staff');
        $camera = $this->createCamera();

        $this->withToken($customer->createToken('test')->plainTextToken)
            ->postJson('/api/maintenances', [
                'camera_id' => $camera->id,
                'issue' => 'Sensor cleaning',
                'scheduled_date' => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();

        Auth::forgetGuards();
        $token = $staff->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/maintenances', [
            'camera_id' => $camera->id,
            'issue' => 'Sensor cleaning',
            'scheduled_date' => now()->addDay()->toDateString(),
            'assigned_to' => 'Jordan Lee',
        ]);

        $response->assertCreated()->assertJsonPath('maintenance.status', 'scheduled');

        $this->withToken($token)
            ->patchJson('/api/maintenances/'.$response->json('maintenance.id'), [
                'status' => 'in-progress',
            ])
            ->assertOk()
            ->assertJsonPath('maintenance.status', 'in-progress');

        $this->assertInstanceOf(Maintenance::class, Maintenance::first());
    }

    public function test_staff_can_process_rental_requests(): void
    {
        $customer = $this->createUser('customer');
        $staff = $this->createUser('staff');
        $rental = Rental::create([
            'user_id' => $customer->id,
            'camera_id' => $this->createCamera()->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'status' => 'pending',
            'total' => 50,
        ]);

        $staffToken = $staff->createToken('test')->plainTextToken;
        $this->withToken($customer->createToken('test')->plainTextToken)
            ->getJson('/api/staff/rentals')
            ->assertForbidden();

        Auth::forgetGuards();
        $response = $this->withToken($staffToken)
            ->getJson('/api/staff/rentals')
            ->assertOk()
            ->assertJsonPath('rentals.0.customer_name', $customer->name);

        $response = $this->withToken($staffToken)
            ->patchJson("/api/staff/rentals/{$rental->id}", [
                'status' => 'confirmed',
            ]);

        $response->assertOk()->assertJsonPath('rental.status', 'confirmed');
        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'status' => 'confirmed',
        ]);
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => 'camera-rental-123',
            'role' => $role,
        ]);
    }

    private function createCamera(): Camera
    {
        return Camera::create([
            'brand' => 'Canon',
            'model' => 'EOS 80D',
            'category' => 'DSLR',
            'daily_rate' => 25,
            'availability' => 'available',
            'description' => 'A DSLR test camera.',
            'specs' => ['24 MP'],
        ]);
    }
}
